define([
    'dojo/_base/lang',
    'dojo/_base/declare',
    'dojo/_base/array',
    'dojo/dom-attr',
    'dojo/date/locale',
    'dojo/promise/all',
    'dijit/_Widget',
    'dijit/_TemplatedMixin',
    'phpr/Api',
    'phpr/Timehelper',
    'phpr/models/Timecard',
    'dojo/text!phpr/template/statistics/WorktimeMonthGraph.html',
    'd3/d3.v3.js'
], function(
    lang,
    declare,
    array,
    domAttr,
    locale,
    all,
    Widget,
    Templated,
    api,
    timehelper,
    timecardModel,
    templateString
) {

    var maxMinutes = 60 * 15,
        barPadding = 2;

    var GeometryHelper = declare(null, {
        _svgNode: null,
        _dayEntries: null,

        constructor: function(svgNode, dayEntries) {
            this._dayEntries = dayEntries;
            this._svgNode = svgNode;
        },

        todayX: function() {
            return (new Date()).getDate() * (this.barWidth() + barPadding) - (barPadding / 2);
        },

        barWidth: function() {
            return (this.displayWidth() / this._dayEntries.length) - barPadding;
        },

        displayWidth: function() {
            return domAttr.get(this._svgNode, 'width') - 40;
        },

        heightPerMinute: function() {
            return this.heightForTimebars() / maxMinutes;
        },

        heightForTimebars: function() {
            return domAttr.get(this._svgNode, 'height');
        }
    });

    var renderMinutesBookedBlocks = (function() {
        var helper;

        function x(d, i) {
            return i * (barPadding + helper.barWidth());
        }

        function height(d) {
            return Math.max(2, helper.heightPerMinute() * d.minutesBooked);
        }

        function y(d) {
            var x = Math.min(
                helper.heightForTimebars() - 2,
                helper.heightForTimebars() - helper.heightPerMinute() * d.minutesBooked
            );
            return x;
        }

        function fill(entry) {
            if (!entry.hasOwnProperty('minutesToWork')) {
                return 'white';
            }
            return entry.minutesBooked < entry.minutesToWork ? '#b5b5b5' : 'white';
        }

        function titleText(d) {
            var date = locale.format(timehelper.dateToJsDate(d.date), {selector: 'date'});
            if (d.minutesBooked !== 0) {
                date += ' (' + timehelper.minutesToHMString(d.minutesBooked) + ')';
            }
            return date;
        }

        return function(svgNode, dayEntries) {
            helper = new GeometryHelper(svgNode, dayEntries);

            var svg = d3.select(svgNode),
                svgData = svg.selectAll().data(dayEntries);

            svgData.enter()
                .append('svg:rect')
                    .attr('fill', fill)
                    .attr('x', x)
                    .attr('y', y)
                    .attr('width', lang.hitch(helper, helper.barWidth))
                    .attr('height', height)
                    .append('svg:title')
                        .text(titleText);
        };
    })();

    var renderTimeToWorkLine = (function() {
        var dayEntries,
            helper;

        function renderHorizontalLines(svgData) {
            svgData.enter()
                .append('svg:line')
                    .attr('x1', horizontalX1)
                    .attr('x2', horizontalX2)
                    .attr('y1', horizontalY)
                    .attr('y2', horizontalY)
                    .attr('stroke', '#6aa700');
        }

        function horizontalX1(d, i) {
            return i * (barPadding + helper.barWidth());
        }

        function horizontalX2(d, i) {
            return (i + 1) * (barPadding + helper.barWidth());
        }

        function horizontalY(d, i) {
            return helper.heightForTimebars() - helper.heightPerMinute() * d.minutesToWork;
        }

        function renderConnectingVerticalLines(svgData) {
            svgData.enter()
                .append('svg:line')
                    .attr('x1', verticalX1)
                    .attr('x2', verticalX2)
                    .attr('y1', verticalY1)
                    .attr('y2', horizontalY)
                    .attr('stroke', '#6aa700');
        }

        function verticalX1(d, i) {
            return i * (barPadding + helper.barWidth());
        }

        function verticalX2(d, i) {
            return i * (barPadding + helper.barWidth());
        }

        function verticalY1(d, i) {
            if (i === 0) {
                return horizontalY(d, i);
            }
            return horizontalY(dayEntries[i - 1], i - 1);
        }

        return function(svgNode, dayEntriesList) {
            dayEntries = dayEntriesList;
            helper = new GeometryHelper(svgNode, dayEntries);

            var svg = d3.select(svgNode);
            var svgData = svg.selectAll().data(dayEntries);

            renderHorizontalLines(svgData);
            renderConnectingVerticalLines(svgData);
        };
    })();

    return declare([Widget, Templated], {
        templateString: templateString,
        baseClass: 'thisMonthDiagram',

        buildRendering: function() {
            this.inherited(arguments);

            if (this._destroyed === true) {
                return;
            }

            this._updateLabels();

            timecardModel.getWorkBalanceByDay().then(
                lang.hitch(this, this._renderUsingWorkBalance),
                lang.hitch(this, function(error) {
                    // fallback rendering, probably no contract
                    api.defaultErrorHandler(error);
                    timecardModel.getMonthList().then(lang.hitch(this, this._renderUsingMonthList));
                })
            );
        },

        _renderUsingWorkBalance: function(data) {
            if (this._destroyed === true) {
                return;
            }

            var entries = [];
            for (var date in data.workBalancePerDay) {
                entries.push({
                    date: date,
                    minutesBooked: data.workBalancePerDay[date].minutesBooked,
                    minutesToWork: data.workBalancePerDay[date].minutesToWork
                });
            }
            renderMinutesBookedBlocks(this.bookedTimePerDayGraph, entries);
            renderTimeToWorkLine(this.bookedTimePerDayGraph, entries);

            this._fillOvertimeLabel();
            this._renderTodayMarker(this.bookedTimePerDayGraph, entries);
        },

        _renderUsingMonthList: function(data) {
            if (this._destroyed === true) {
                return;
            }

            var entries = [];
            array.forEach(data.days, function(entry) {
                entries.push({
                    date: entry.date,
                    minutesBooked: entry.sumInMinutes
                });
            });
            renderMinutesBookedBlocks(this.bookedTimePerDayGraph, entries);

            this._renderTodayMarker(this.bookedTimePerDayGraph, entries);
        },

        _fillOvertimeLabel: function() {
            timecardModel.getMonthStatistics().then(lang.hitch(this, function(result) {
                if (this._destroyed === true) {
                    return;
                }

                var overtime = result.booked.minutesBooked - result.towork.minutesToWork;
                this.overtimeLabel.innerHTML = timehelper.minutesToHMString(overtime) + ' Overtime';
            }), function(err) {
                api.defaultErrorHandler(err);
            });
        },

        _renderTodayMarker: function(domNode, entries) {
            var svg = d3.select(this.bookedTimePerDayGraph),
                helper = new GeometryHelper(domNode, entries);

            svg.append('rect')
                .attr('x', helper.todayX() - 1)
                .attr('width', 2)
                .attr('y', 0)
                .attr('height', helper.heightForTimebars())
                .attr('fill', '#0d639b');
        },

        _updateLabels: function() {
            var thisYear = (new Date()).getFullYear(),
                thisMonth = (new Date()).getMonth(),
                first = new Date(thisYear, thisMonth, 1, 0, 0, 0),
                last = new Date(thisYear, thisMonth + 1, 0, 0, 0, 0);
            this.firstDayLabel.innerHTML = locale.format(first, {selector: 'date', datePattern: 'EEE d'});
            this.lastDayLabel.innerHTML = locale.format(last, {selector: 'date', datePattern: 'EEE d'});
        }
    });
});

