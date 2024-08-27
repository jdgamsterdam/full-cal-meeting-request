patch -p1 ./../../fullcalendar_view/fullcalendar_view.info.yml < ./patches/fullcalendar_view.info.yml
patch -p1 ./../../fullcalendar_view/js/fullcalendar_view.js < ./patches/fullcalendar_view_js.patch
patch -p1 ./../../fullcalendar_view/src/FullcalendarViewPreprocess.php < ./patches/fullcalendar_view_main.patch
patch -p1 ./../../fullcalendar_view/src/Plugin/views/style/FullCalendarDisplay.php < ./patches/fullcalendar_view_display.patch
