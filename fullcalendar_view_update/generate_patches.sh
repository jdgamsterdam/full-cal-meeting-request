git diff --no-index ./../../fullcalendar_view/fullcalendar_view.info.yml ./assets/fullcalendar_view.info.yml > ./patches/fullcalendar_view.info.yml

git diff --no-index ./../../fullcalendar_view/js/fullcalendar_view.js ./assets/js/fullcalendar_view.js > ./patches/fullcalendar_view_js.patch

git diff --no-index  ./../../fullcalendar_view/src/FullcalendarViewPreprocess.php ./assets/src/FullcalendarViewPreprocess.php > ./patches/fullcalendar_view_main.patch

git diff --no-index  ./../../fullcalendar_view/src/Plugin/views/style/FullCalendarDisplay.php ./assets/src/Plugin/views/style/FullCalendarDisplay.php > ./patches/fullcalendar_view_display.patch
