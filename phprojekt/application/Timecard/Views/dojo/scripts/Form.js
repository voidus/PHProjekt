/**
 * This software is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License version 2.1 as published by the Free Software Foundation
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * @copyright  Copyright (c) 2008 Mayflower GmbH (http://www.mayflower.de)
 * @license    LGPL 2.1 (See LICENSE file)
 * @version    $Id$
 * @author     Gustavo Solt <solt@mayflower.de>
 * @package    PHProjekt
 * @link       http://www.phprojekt.com
 * @since      File available since Release 6.0
 */

dojo.provide("phpr.Timecard.Form");

dojo.declare("phpr.Timecard.Form", phpr.Component, {
    sendData:      new Array(),
    formdata:      new Array(),
    dateObject:    null,
    _hourUrl:      null,
    _bookUrl:      null,
    _favoritesUrl: null,
    _formNode:     null,
    _date:         null,
    _contentBar:   null,
    _surface:      null,

    constructor:function(main, id, module, date) {
        // summary:
        //    render the form on construction
        // description:
        //    this function receives the form data from the server and renders the corresponding form
        //    If the module is a param, is setted
        this.main = main;
        this.id   = id;

        // Fixed hours 8-20
        var hours = new Array();
        var pair  = 0;
        var show  = '';
        for (var i = 8; i < 21; i++) {
            show = '';
            if ((i % 2) == 0) {
                pair = 1;
            } else {
                pair = 0;
            }
            if (i.lenght == 1) {
                show = '0';
            }
            show = show + i + ':00';
            hours.push({"hour": show, "pair": pair});
        }

        this.render(["phpr.Timecard.template", "form.html"], dojo.byId('detailsBox'), {
            hours:                    hours,
            timecardProjectTimesText: phpr.nls.get("Project Bookings"),
            projectTimesHelpText:     phpr.nls.get("Project Times Help"),
            manageFavoritesText:      phpr.nls.get('Manage project list')
        });
        dojo.connect(dijit.byId('manageFavorites'), "hide",  dojo.hitch(this, "submitFavoritesForm"));

        this._contentBar = new phpr.Timecard.ContentBar("projectBookingContainer");


        this.setDate(date);
        this.loadView();
        this.reloadDateView();
    },

    setDate:function(date) {
        // summary:
        //    Set the date for use in the form
        // description:
        //    Set the date for use in the form
        if (undefined == date) {
            this.dateObject = new Date();
        } else {
            this.dateObject = date;
        }
        this._date = phpr.Date.getIsoDate(this.dateObject);
    },

    loadView:function() {
        // summary:
        //    Load all the views
        // description:
        //    Load all the views
        this.setHourUrl();
        this.setBookUrl();
        this.getFormData(1, 1, 1);
    },

    setHourUrl:function() {
        // summary:
        //    Set the url for get the data
        // description:
        //    Set the url for get the data
        this._hourUrl = phpr.webpath + "index.php/" + phpr.module + "/index/jsonDetail/date/" + this._date
    },

    setBookUrl:function() {
        // summary:
        //    Set the url for get the data
        // description:
        //    Set the url for get the data
        this._bookUrl      = phpr.webpath + "index.php/" + phpr.module + "/index/jsonBookingDetail/date/" + this._date
        this._favoritesUrl = phpr.webpath + "index.php/" + phpr.module + "/index/jsonGetFavoritesProjects";
    },

    getFormData:function(hours, date, books) {
        // summary:
        //    Reload only the views that are needed
        // description:
        //    Reload only the views that are needed
        if (date) {
            this.reloadDateView();
        }

        if (books) {
            phpr.DataStore.addStore({url: this._favoritesUrl});
            phpr.DataStore.requestData({url: this._favoritesUrl, processData: dojo.hitch(this, function() {
                    phpr.DataStore.addStore({url: this._bookUrl});
                    phpr.DataStore.requestData({
                        url:         this._bookUrl,
                        processData: dojo.hitch(this, "reloadBookingView")
                    });
                })
            });
        }

        if (hours) {
            // Render the form element on the right bottom
            phpr.DataStore.addStore({url: this._hourUrl});
            phpr.DataStore.requestData({url: this._hourUrl, processData: dojo.hitch(this, "reloadHoursView")});
        }
    },

    reloadHoursView:function() {
        // summary:
        //    Reload the Hours view
        // description:
        //    Reload the Hours view with the form and all the hours saved for the current day
        var hoursdata  = "";
        var totalHours = 0;
        var meta       = phpr.DataStore.getMetaData({url: this._hourUrl});
        var data       = phpr.DataStore.getData({url: this._hourUrl});

        for (var i = 0; i < data.length; i++) {
            var diffTime = this.getDiffTime(data[i].endTime, data[i].startTime);
            if (diffTime > 0) {
                totalHours += diffTime;
            }
            hoursdata += this.render(["phpr.Timecard.template", "hours.html"], null, {
                hoursDiff: phpr.Date.convertTime(this.getDiffTime(data[i].endTime, data[i].startTime)),
                start:     data[i].startTime.substr(0, 5),
                end:       data[i].endTime.substr(0, 5),
                id:        data[i].id
            });
        }

        this.render(["phpr.Timecard.template", "hoursForm.html"], dojo.byId('TimecardHours'), {
            date: this._date,
            timecardWorkingTimesText: phpr.nls.get("Working Times"),
            workingTimesHelpText:     phpr.nls.get("Working Times Help"),
            hoursHelpText:            phpr.nls.get("Hours Help"),
            timecardStartText:        phpr.nls.get("Start"),
            timecardEndText:          phpr.nls.get("End"),
            timecardTotalText:        phpr.nls.get("Total"),
            hoursdata:                hoursdata,
            totalHours:               phpr.Date.convertTime(totalHours)
        });

        for (var i = 0; i < data.length; i++) {
            dojo.connect(dijit.byId("deleteHourButton_" + data[i].id), "onClick",
                dojo.hitch(this, "deleteForm", [data[i].id]));
        }
        dojo.connect(dijit.byId("hoursSaveButton"), "onClick", dojo.hitch(this, "submitForm"));
    },

    reloadDateView:function() {
        // summary:
        //    Reload the Date view
        // description:
        //    Reload the HDate picker div with the current date
        var month   = this.dateObject.getMonth();
        var year    = this.dateObject.getFullYear();
        var dd      = new Date(year, month, 0);
        var lastDay = dd.getDate() + 1;
        var week    = dd.getDay();
        var days    = new Array();
        var today   = this.dateObject.getDate();

        var months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'Agoust', 'September',
            'October', 'November', 'December'];
        var weeks = ['Monday', 'Tuesday', 'Wenesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        var monthString = phpr.nls.get(months[month-1]);

        for (var i = 1; i < lastDay; i++) {
            var weekString = phpr.nls.get(weeks[week]);
            days.push({'day': i, 'month': monthString, 'week': weekString});
            week++;
            if (week > 6) {
                week = 0;
            }
        }
    },

    reloadBookingView:function() {
        // summary:
        //    Reload the Booking view
        // description:
        //    Reload the Booking view with the form and all the Bookings saved for the current day
        var meta         = phpr.DataStore.getMetaData({url: this._bookUrl});
        var data         = phpr.DataStore.getData({url: this._bookUrl});
        var favorites    = phpr.DataStore.getData({url: this._favoritesUrl});
        var range        = meta[1]['range'];
        var timeprojData = data['timeproj'];
        var timecardData = data['timecard'];
        var hourHeight   = 20;

        timecardProjectPositions = new Array();

        // Show Bookings forms
        this.showBookingForm(timeprojData, range);

        // Get Favorites
        var favoritesList = new Array();
        for (var k in favorites) {
             for (var j in range) {
                if (range[j]['id'] == favorites[k]) {
                    favoritesList.push(range[j]);
                }
            }
        }

        // Get All Projects
        var allProjects   = new Array();
        for (var j in range) {
            var found = false;
            for (var k in favorites) {
                if (range[j]['id'] == favorites[k]) {
                    found = true;
                }
            }
            if (!found) {
                allProjects.push(range[j]);
            }
        }

        // Show Drag and Drop Projects
        this.showProjectsSources(favoritesList);

        // Make Dialog
        this.createFavoritesDialog(allProjects, favoritesList);

        // Clean "Day View"
        dijit.byId("projectBookingContainer").destroyDescendants();
        this._surface   = dojox.gfx.createSurface('projectBookingContainer', 400, 260);
        var lastHour    = 0;
        var totalHeight = 0;
        for (j in timeprojData) {
            timeprojData[j].displayed = 0;
            timeprojData[j].remaind   = 0;
        }

        // Draw hours block
        for (i in timecardData) {
            var start  = this._contentBar.convertHourToPixels(hourHeight, timecardData[i].startTime);
            var end    = this._contentBar.convertHourToPixels(hourHeight, timecardData[i].endTime);
            var top    = start + 'px';
            var finish = 0;
            if ((end - start) - 6 < 0) {
                var height = (end - start) + 'px';
            } else {
                var height = (end - start) - 6 + 'px';
            }
            var totalbookingHeight = 0;

            totalHeight += (end - start);

            var tmp       = dojo.doc.createElement("div");
            tmp.id        = 'targetBooking' + timecardData[i].id;
            tmp.innerHTML = '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;';
            dojo.addClass(tmp, "dndTarget");
            dojo.style(tmp, "top", top);
            dojo.style(tmp, "height", height);
            dijit.byId("projectBookingContainer").domNode.appendChild(tmp);
            var target = new phpr.Timecard.Booking(tmp.id);

            // Draw Bookings
            for (j in timeprojData) {
                if (timeprojData[j].displayed == 0) {
                    if (timeprojData[j].remaind > 0) {
                        var bookingHeight = timeprojData[j].remaind;
                    } else {
                        var bookingHeight = this._contentBar.convertAmountToPixels(hourHeight, timeprojData[j].amount);
                    }
                    if (totalbookingHeight <= (end - start)) {
                        if (lastHour == 0) {
                            lastHour = start;
                        }
                        var check = end - lastHour;
                        if (bookingHeight > check) {
                            timeprojData[j].remaind = bookingHeight - check;
                            if (timeprojData[j].remaind > 0) {
                                finish = 1;
                                bookingHeight = check;
                            }
                        }
                        if (bookingHeight > 0) {
                            var tmpDraw = this._surface.createRect({
                                y:       lastHour + 2,
                                x:       1,
                                height:  bookingHeight - 3,
                                width:   197,
                                r:       13
                            });
                            tmpDraw.setFill([68, 74, 82, 1]);
                            tmpDraw.setStroke({color: [68, 74, 82, 1], width: 2});
                            timecardProjectPositions.push({
                                'start': lastHour,
                                'end':   lastHour + bookingHeight,
                                'id':    timeprojData[j].id});

                            phpr.Gfx.makeText(this._surface, {
                                x:     15,
                                y:     lastHour + 15,
                                text:  timeprojData[j].projectName,
                                align: "start"},
                                {family: "Verdana", size: "8pt"}, "white", "white");
                        }

                        if (finish) {
                            // Draw a red fill if the project booking exceed the time
                            var next = parseInt(i) + 1;
                            if (timeprojData[j].remaind > 0 && typeof(timecardData[next]) == "undefined") {
                                lastHour    = lastHour + bookingHeight;
                                var tmpDraw = this._surface.createRect({
                                    y:      lastHour + 2,
                                    x:      1,
                                    height: timeprojData[j].remaind - 3,
                                    width:  197,
                                    r:      13
                                });
                                tmpDraw.setFill("red");
                                tmpDraw.setStroke({color: "red", width: 2});

                                phpr.Gfx.makeText(this._surface, {
                                    x:     15,
                                    y:     lastHour + 15,
                                    text:  timeprojData[j].projectName,
                                    align: "start"},
                                    {family: "Verdana", size: "8pt"}, "white", "white");
                            }
                            totalbookingHeight += bookingHeight + end;
                            lastHour = 0;
                        } else {
                            totalbookingHeight += bookingHeight;
                            lastHour           += bookingHeight;
                            timeprojData[j].displayed = 1;
                        }
                    }
                }
            }
        }
    },

    showBookingForm:function(timeprojData, range) {
        // summary:
        //    Render the booking form
        // description:
        //    Render the booking form for all the projects saved and a empty one for add
        var bookingdata  = '';

        // Collect all the bookings forms
        for (var i = 0; i < timeprojData.length; i++) {
            var projectName = '';
            for (var j in range) {
                if (range[j]['id'] == timeprojData[i].projectId) {
                    var projectName = range[j]['name'];
                    if (projectName.length > 25) {
                        projectName = projectName.substr(0, 25) + '...';
                    }
                    timeprojData[i].projectName = projectName;
                }
            }
            bookingdata += this.render(["phpr.Timecard.template", "bookings.html"], null, {
                projectName:    projectName,
                projectId:      timeprojData[i].projectId,
                projectIdLabel: phpr.nls.get('Project'),
                date:           this._date,
                notes:          timeprojData[i].notes,
                notesLabel:     phpr.nls.get('Notes'),
                amount:         phpr.Date.convertTime(this.getDiffTime(timeprojData[i].amount, '00:00:00')),
                amountLabel:    phpr.nls.get('Amount [hhmm]'),
                saveText:       phpr.nls.get('Save'),
                deleteText:     phpr.nls.get('Delete'),
                id:             timeprojData[i].id
            });
        }

        // New one
        bookingdata += this.render(["phpr.Timecard.template", "bookings.html"], null, {
            projectName:    '',
            projectId:      0,
            projectIdLabel: phpr.nls.get('Project'),
            date:           this._date,
            notes:          '',
            notesLabel:     phpr.nls.get('Notes'),
            amount:         '',
            amountLabel:    phpr.nls.get('Amount [hhmm]'),
            saveText:       phpr.nls.get('Save'),
            deleteText:     phpr.nls.get('Cancel'),
            id:             0
        });

        dijit.byId('projectBookingData').attr('content', bookingdata);

        // Event buttons
        for (j in timeprojData) {
            dojo.connect(dijit.byId("deleteBookingButton_" + timeprojData[j].id), "onClick",
                dojo.hitch(this, "deleteBookingForm", [timeprojData[j].id]));
            dojo.connect(dijit.byId("saveBookingButton_" + timeprojData[j].id), "onClick",
                dojo.hitch(this, "submitBookingForm", [timeprojData[j].id]));
        }

        dojo.connect(dijit.byId("deleteBookingButton_0"), "onClick", function(){
            var node = dojo.byId('projectBookingForm_0');
            if (node) {
                dojo.style(node, "display", "none");
            }
        });
        dojo.connect(dijit.byId("saveBookingButton_0"), "onClick", dojo.hitch(this, "submitBookingForm", [0]));
    },

    showProjectsSources:function(values) {
        // summary:
        //    Render the projects for drag and drop
        // description:
        //    Render the projects for drag and drop
        //    Destroy the oldest first
        for (i in values) {
            phpr.destroyWidget(values[i].id);
        }
        var html = this.render(["phpr.Timecard.template", "projectSource.html"], null, {
            values: values
        });

        dojo.byId('projectBookingSource').innerHTML = html;
        projectBookingSource.sync();
    },

    createFavoritesDialog:function(allProjects, favoritesList) {
        // summary:
        //    Render the dialog for manage favorites
        // description:
        //    Render the dialog for manage favorites
        for (i in allProjects) {
            phpr.destroyWidget('favoritesTarget-' + allProjects[i].id);
        }
        for (i in favoritesList) {
            phpr.destroyWidget('favoritesSoruce-' + favoritesList[i].id);
        }
        var html = this.render(["phpr.Timecard.template", "favoritesDialog.html"], dojo.byId('dialogContent'), {
            allProjects:   allProjects,
            favoritesList: favoritesList
        });
    },

    prepareSubmission:function() {
        // summary:
        //    Correct some data before send it to the server
        // description:
        //    Correct some data before send it to the server
        if (this.sendData.endTime) {
            this.sendData.endTime = phpr.Date.getIsoTime(this.sendData.endTime);
        }
        if (this.sendData.startTime) {
            this.sendData.startTime = phpr.Date.getIsoTime(this.sendData.startTime);
        }
        if (this.sendData.amount) {
            this.sendData.amount = phpr.Date.getIsoTime(this.sendData.amount);
        }
        return true;
    },

    submitForm:function() {
        // summary:
        //    Save the hours form
        // description:
        //    Save the hours form and reload only the grid and the hours form
        this.sendData = new Array();
        this.sendData = dojo.mixin(this.sendData, dijit.byId('hoursForm').attr('value'));
        if (!this.prepareSubmission()) {
            return false;
        }

        phpr.send({
            url:       phpr.webpath + 'index.php/Timecard/index/jsonSave/',
            content:   this.sendData,
            onSuccess: dojo.hitch(this, function(data) {
               new phpr.handleResponse('serverFeedback', data);
                if (data.type == 'success') {
                    this.publish("updateCacheData");
                    phpr.DataStore.deleteData({url: this._hourUrl});
                    phpr.DataStore.deleteData({url: this._bookUrl});
                    this.getFormData(1, 0, 1);
                    this.main.grid.reloadView(this.main._view, this.main._date.getFullYear(),
                        (this.main._date.getMonth() + 1));
               }
            })
        });
    },

    submitBookingForm:function(id) {
        // summary:
        //    Save the booking form
        // description:
        //    Save the booking form and reload only the grid and the booking form
        this.sendData = new Array();
        this.sendData = dojo.mixin(this.sendData, dijit.byId('bookingForm_' + id).attr('value'));
        if (!this.prepareSubmission()) {
            return false;
        }

        phpr.send({
            url:       phpr.webpath + 'index.php/Timecard/index/jsonBookingSave/id/' + id,
            content:   this.sendData,
            onSuccess: dojo.hitch(this, function(data) {
               new phpr.handleResponse('serverFeedback', data);
                if (data.type == 'success') {
                    this.publish("updateCacheData");
                    phpr.DataStore.deleteData({url: this._bookUrl});
                    this.getFormData(1, 0, 1);
                    this.main.grid.reloadView(this.main._view, this.main._date.getFullYear(),
                        (this.main._date.getMonth() + 1));
               }
            })
        });
    },

    submitFavoritesForm:function() {
        // summary:
        //    Save the favorites projects
        // description:
        //    Save the favorites projects
        this.sendData                = new Array();
        this.sendData['favorites[]'] = new Array();
        var favorites = dojo.byId('selectedProjectFavorites').value.split(",");
        for (var i in favorites) {
            var value = parseInt(favorites[i]);
            if (value > 0) {
                this.sendData['favorites[]'].push(value);
            }
        }

        phpr.send({
            url:       phpr.webpath + 'index.php/Timecard/index/jsonFavortiesSave',
            content:   this.sendData,
            onSuccess: dojo.hitch(this, function(data) {
               new phpr.handleResponse('serverFeedback', data);
                if (data.type == 'success') {
                    phpr.DataStore.deleteData({url: this._favoritesUrl});
               }
            })
        });
    },

    deleteForm:function(id) {
        // summary:
        //    Delete an hour saved
        // description:
        //    Delete an hour saved and reload only the grid and the hours form
        phpr.send({
            url:       phpr.webpath + 'index.php/' + phpr.module + '/index/jsonDelete/id/' + id,
            onSuccess: dojo.hitch(this, function(data) {
                new phpr.handleResponse('serverFeedback', data);
                if (data.type == 'success') {
                    this.publish("updateCacheData");
                    phpr.DataStore.deleteData({url: this._hourUrl});
                    phpr.DataStore.deleteData({url: this._bookUrl});
                    this.getFormData(1, 0, 1);
                    this.main.grid.reloadView(this.main._view, this.main._date.getFullYear(),
                        (this.main._date.getMonth() + 1));
               }
            })
        });
    },

    deleteBookingForm:function(id) {
        // summary:
        //    Delete an booking saved
        // description:
        //    Delete an booking saved and reload only the grid and the booking form
        phpr.send({
            url:       phpr.webpath + 'index.php/' + phpr.module + '/index/jsonBookingDelete/id/' + id,
            onSuccess: dojo.hitch(this, function(data) {
                new phpr.handleResponse('serverFeedback', data);
                if (data.type == 'success') {
                    this.publish("updateCacheData");
                    phpr.DataStore.deleteData({url: this._bookUrl});
                    this.getFormData(0, 0, 1);
                    this.main.grid.reloadView(this.main._view, this.main._date.getFullYear(),
                        (this.main._date.getMonth() + 1));
               }
            })
        });
    },

    updateData:function() {
        // summary:
        //    Delete the cache for this form
        // description:
        //    Delete the cache for this form
    },

    getDiffTime:function(end, start) {
        // summary:
        //    Ger the diff in minutes between two times
        // description:
        //    Ger the diff in minutes between two times
        var hoursEnd     = end.substr(0, 2);
        var minutesEnd   = end.substr(3, 2);
        var hoursStart   = start.substr(0, 2);
        var minutesStart = start.substr(3, 2);

        return ((hoursEnd - hoursStart)*60) + (minutesEnd - minutesStart);
    }
});
