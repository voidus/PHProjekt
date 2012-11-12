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
 * @copyright  Copyright (c) 2010 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL v3 (See LICENSE file)
 */

dojo.provide("phpr.Timecard.ContractsGridDialog");

dojo.declare("phpr.Timecard.ContractsGridDialog", phpr.Default.Grid, {
    getRestUrl: function() {
        return "index.php/Timecard/Contract/";
    },

    constructor: function() {
        this._resizeSubscribe = dojo.subscribe("phpr.resize", this, '_onResize');
        this.setContainer();
        this.addCreateButton();
    },

    destroy: function() {
        this.inherited(arguments);
        dojo.unsubscribe(this._resizeSubscribe);
        this._resizeSubscribe = null;
        this.dialog.destroyRecursive();
        this.dialog = null;
    },

    setContainer: function(container) {
        debugger;
        this.node = new dijit.layout.ContentPane({style: "width: 100%; height: 100%; overflow: hidden;"});
        this.buttons = new dijit.layout.ContentPane({style: "width: 100%; height: 30px; padding-top: 10px;"});
        //draggable = false must be set because otherwise the dialog can not be closed on the ipad
        //bug: http://bugs.dojotoolkit.org/ticket/13488
        this.dialog = new dijit.Dialog({style: "width: 80%; height: 80%;", draggable: false});

        this.dialog.show();

        this.dialog.containerNode.appendChild(this.node.domNode);
        this.dialog.containerNode.appendChild(this.buttons.domNode);

        this._setNodeSizes();

        this.node.startup();
        this.garbageCollector.addNode(this.node);
        this.garbageCollector.addNode(this.buttons);
        this.garbageCollector.addNode(this.dialog);

        // remove the form opening part from the url
        this.garbageCollector.addEvent(
            dojo.connect(this.dialog, "onHide",
                dojo.hitch(this, function() {
                    phpr.pageManager.modifyCurrentState({
                            id: undefined
                        }, {
                            noAction: true
                        }
                    );
                })));
    },

    _setNodeSizes: function() {
        var dialogBox = dojo.contentBox(this.dialog.domNode);
        var dialogTitleBox = dojo.contentBox(this.dialog.titleBar);
        var dialogContainerBox = dojo.contentBox(this.dialog.containerNode);

        dojo.style(this.dialog.containerNode, {
            height: (dialogBox.h - dialogTitleBox.h - dialogContainerBox.t - 50) + 'px',
            width: dialogTitleBox.w - dialogContainerBox.l + 'px'
        });
    },

    _onResize: function() {
        this.dialog.resize();
        this._setNodeSizes();
        this.form.resize();
    },

    setExportButton: function() {
    },

    setFilterButton: function() {
    },

    addCreateButton: function() {
        // Summary:
        //    Create the Add button
        this.garbageCollector.collect('newEntry');
        var params = {
            label:     phpr.nls.get('Add a new item'),
            showLabel: true,
            baseClass: "positive",
            iconClass: 'add'
        };
        var newEntry = new dijit.form.Button(params);
        this.buttonRow.domNode.appendChild(newEntry.domNode);
        this.garbageCollector.addNode(newEntry, 'newEntry');
        this.garbageCollector.addEvent(
            dojo.connect(newEntry, "onClick", dojo.hitch(this, "newEntry")),
            'newEntry'
        );
    }
});
