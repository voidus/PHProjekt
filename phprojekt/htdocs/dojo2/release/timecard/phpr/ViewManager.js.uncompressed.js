define("phpr/ViewManager", [
    'dojo/_base/declare',
    'dijit/Destroyable',
    'dojo/_base/lang',
    'dojo/_base/array',
    'dojo/topic',
    'phpr/BookingView',
    'phpr/StatisticsView'
], function(declare, destroyable, lang, array, topic, BookingView, StatisticsView) {
    return declare(destroyable, {
        baseLayout: null,

        constructor: function(baseLayout) {
            this.baseLayout = baseLayout;
            var eventmap = {
                'phpr/showLiveBooking': 'onLiveBooking',
                'phpr/showBookings': 'onBookings',
                'phpr/showStatistics': 'onStatistics'
            };

            for (var top in eventmap) {
                this.own(topic.subscribe(top, lang.hitch(this, eventmap[top])));
            }
        },

        startup: function() {
            this.inherited(arguments);
            this.baseLayout.menubar.onBookingsClick();
        },

        onLiveBooking: function() {
            this.baseLayout.mainContent.set('content', 'imagine a timecard here');
        },

        onBookings: function() {
            this.baseLayout.mainContent.set('content', new BookingView());
        },

        onStatistics: function() {
            this.baseLayout.mainContent.set('content', new StatisticsView());
        }
    });
});
