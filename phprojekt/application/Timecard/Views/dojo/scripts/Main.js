/**
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 3 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * @category  PHProjekt
 * @package   Template
 * @copyright Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license   LGPL v3 (See LICENSE file)
 * @link      http://www.phprojekt.com
 * @since     File available since Release 6.0
 * @version   Release: 6.1.0
 * @author    Gustavo Solt <solt@mayflower.de>
 */

dojo.provide("phpr.Timecard.Main");

dojo.require("dijit.ColorPalette");
dojo.require("phpr.Timecard.ContractsGrid");

dojo.declare("phpr.Timecard.Main", phpr.Default.Main, {
    stackContainer: null,

    constructor: function() {
        this.module = "Timecard";
        this.loadFunctions(this.module);

        this.formWidget = phpr.Timecard.Form;
    },

    renderTemplate: function() {
        phpr.viewManager.setView(
            phpr.Default.System.DefaultView,
            phpr.Timecard.ViewContentMixin
        );
    },

    setWidgets: function() {
        this.stackContainer = new dijit.layout.StackContainer();
        this.grid = new phpr.Timecard.GridWidget({
            store: new dojo.store.JsonRest({target: 'index.php/Timecard/Timecard/'})
        });
        this.stackContainer.addChild(this.grid);

        this.contractsGridPane = new dijit.layout.ContentPane();
        this.contractsGrid = phpr.Timecard.ContractsGrid('', this, null, this.contractsGridPane, {});
        this.stackContainer.addChild(this.contractsGridPane);

        phpr.viewManager.getView().gridBox.set('content', this.stackContainer);
        this.addContractsButton();
        this.addExportButton();
        this.setTimecardCaldavClientButton();
    },

    addExportButton: function() {
        var params = {
            label:     phpr.nls.get('Export to CSV'),
            showLabel: true,
            baseClass: "positive",
            iconClass: "export",
            disabled:  false
        };
        this._exportButton = new dijit.form.Button(params);

        this.garbageCollector.addNode(this._exportButton);

        phpr.viewManager.getView().buttonRow.domNode.appendChild(this._exportButton.domNode);

        this._exportButton.subscribe(
            "timecard/yearMonthChanged",
            dojo.hitch(this, function(year, month) {
                if (this._exportButtonFunction) {
                    dojo.disconnect(this._exportButtonFunction);
                }
                this._exportButtonFunction = dojo.connect(
                    this._exportButton,
                    "onClick",
                    dojo.hitch(this, "exportData", year, month)
                );
            })
        );
    },

    addContractsButton: function() {
        var inContractsView = false;

        var contractsLabel = phpr.nls.get('Manage contracts'),
            returnLabel = phpr.nls.get('Return to timecard view');
        var contractsParams = {
            label:     contractsLabel,
            showLabel: true,
            baseClass: "positive",
            disabled:  false
        };
        var contractsButton = new dijit.form.Button(contractsParams);


        this.garbageCollector.addNode(contractsButton);

        phpr.viewManager.getView().buttonRow.domNode.appendChild(contractsButton.domNode);
        contractsButton.connect(contractsButton, "onClick", dojo.hitch(this, function() {
            if (inContractsView) {
                inContractsView = false;
                contractsButton.set("label", contractsLabel);
                this.stackContainer.selectChild(this.grid);
            } else {
                inContractsView = true;
                contractsButton.set("label", returnLabel);
                this.stackContainer.selectChild(this.contractsGridPane);
            }
        }));
    },

    exportData: function(year, month) {
        var start = new Date(year, month, 1),
            end = new Date(year, month + 1, 1);

        var params = {
            csrfToken: phpr.csrfToken,
            format: 'csv',
            filter: dojo.toJson({
                startDatetime: {
                    "!ge": start.toString(),
                    "!lt": end.toString()
                }
            })
        };
        window.open('index.php/Timecard/Timecard/?' + dojo.objectToQuery(params), '_blank');
    },

    setTimecardCaldavClientButton: function() {
        // Summary:
        //    Set the timecardCaldavClient button
        // Description:
        //    Set the timecardCaldavClient button
        this.garbageCollector.collect('timecardCaldavClient');

        var prefix = phpr.getAbsoluteUrl('index.php/Timecard/caldav/index/'),
            url = prefix + 'calendars/' + phpr.config.currentUserName + '/default/',
            iosUrl = prefix + 'principals/' + phpr.config.currentUserName + '/',
            params = {
                label: 'Timecard Caldav Client',
                showLabel: true,
                baseClass: 'positive',
                disabled: false
            },
            timecardCaldavClientButton = new dijit.form.Button(params);

        phpr.viewManager.getView().buttonRow.domNode.appendChild(timecardCaldavClientButton.domNode);

        this.garbageCollector.addNode(timecardCaldavClientButton, 'timecardCaldavClient');
        this.garbageCollector.addEvent(
            dojo.connect(
                timecardCaldavClientButton,
                'onClick',
                dojo.hitch(this, 'showTimecardCaldavClientData', url, iosUrl)
            ),
            'timecardCaldavClient'
        );
    },

    showTimecardCaldavClientData: function(url, iosUrl) {
        var content = phpr.fillTemplate(
            'phpr.Calendar2.template.caldavView.html',
            {
                headline: 'Timecard Caldav Client',
                normalLabel: phpr.nls.get('CalDav url', 'Calendar2'),
                iosLabel: phpr.nls.get('CalDav url for Apple software', 'Calendar2'),
                noticeLabel: phpr.nls.get('Notice', 'Calendar2'),
                notice: phpr.nls.get('Please pay attention to the trailing slash, it is important', 'Calendar2'),
                normalUrl: url,
                iosUrl: iosUrl
            }
        );

        //draggable = false must be set because otherwise the dialog can not be closed on the ipad
        //bug: http://bugs.dojotoolkit.org/ticket/13488
        var dialog = new dijit.Dialog({
            content: content,
            draggable: false
        });

        dialog.show();
        this.garbageCollector.addNode(dialog, 'timecardCaldavClient');
    }
});
