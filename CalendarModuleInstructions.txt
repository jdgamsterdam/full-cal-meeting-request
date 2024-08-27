Full-Cal-Meeting-Request

Intro:

The current Bookable Calendar (https://www.drupal.org/project/bookable_calendar) module is great, and it made me understand a number of things on how a new module for this purpose should be developed.   

However, because of its structure, I found it difficult to integrate with an external calendar, and I prefer the Fullcalendar View (https://www.drupal.org/project/fullcalendar_view) to the Calendar View module (https://www.drupal.org/project/calendar_view).  My system easily allows each drupal user to have their own calendar on the system. 

Also different than every other calendar system in the world, I flipped the methodology so that you define the times that are EXCLUDED rather than what are INCLUDED. 

By doing it this way you don't need to create any time slots for people to book.  People can make an appointment request for any time that is not blocked out by the restrictions or existing appointments in the Free/Busy calendar. 

NOTE: Modules do not necessarily check for everything they need.  This is NOT a 1 button install.  Please follow the instructions.

Modules Needed:

--Because I use CiviCRM for the Users Full Name (composer require drupal/civicrm_entity)
  The system could be easily modified to exclude this or to use a Custom Drupal Name Field. 

--Full Calendar View Plugin (composer require drupal/fullcalendar_view)

  Allows A Free Busy Calendar. As mentioned above. I like the ability to click on a day or time and have the meeting request filled in. 

--Feeds Ical.  (composer require drupal/feeds_ical)
  Used by Many of the Modules and especially necessary to use Feeds to synchronize existing calendar

--Feeds (composer require drupal/feeds, composer require drupal/feeds_tamper)
  If you want to use Feeds to eliminate Current Free Busy

--Create an ics file (or link for google Calendars) Use the Latest Version (2.14) of the eluceo/ical library (composer require eluceo/ical)
  Unfortunately The existing ics Modules (e.g. https://www.drupal.org/project/ics_link_field) Use an Old Version so these cannot be installed

--Allow Calendars for multiple Users (composer require drupal/views_selective_filters)
  Needed to Select the User ID and Filter the Calendar

  (all above are enabled in the shell script required_modules.sh)

-- The Custom Modules (and Patches) in the required order

1. twig_functions_xtra
  This has a number of useful Twig Functions that I use in my development.   For thise project there is one specific function that gets a users Time Zone from the Users ID (amazingly this is not possible in a standard Twig or Token Function).  So if you want to use time zones this is necessary

2. calendar_restriction_instances

  Creates a User Role Called "Calendar Owner" 
    This is necessary to create calendars for multiple users. Does not need any permissions (other than standard ) but could be associated with other things. Will be deleted on uninstall

  Creates a node that mimics individual iCal records.  In addition to the standard iCal field there is a reference field to the owner of the calendar. In its current code it only allows 1 owner, but no real reason not to be able to include more. 

  NOTE: Make Sure none of the Existing field names exist from your other custom modules 
    
3. cal_restrict_webform_handler

  This module does a few things: 
   a. Creates a Webform which allows you to specify the calendar restrictions for a specific user. At the moment this form has a single line with the Day of the Week and the times to exclude.  This should be fairly easy to expand to monthly repetitions.
   b. A custom Webform Handler that "explodes" each of these restrictions into individual nodes as well as makes sure that Start Time is Before End Time
   c. Whenever a new submission (or an edit of an old submission) is made all the old restrictions are removed and replaced with the new values. It will only remove those lines with a UID (e.g. those downloaded from a calendar) of the person making the submission and that have "Custom Restriction" in the field_ical_record_uid.  So if you want to create a manual specific restriction, then put something else into the field_ical_record_uid (e.g. not "Custom Restriction" or it will be deleted). 

4. A custom Feed Type, "Outlook To Calendar Restrictions" to Import your Outlook Calendar.  This is very basic and can be expanded to meet your requirements.

The files are in the folder outlook_import_feed_type.  It should be possible to have these automatically installed in a module, but if they do not get done in the correct order it will not work.

The following can be imported with the Single Import: /admin/config/development/configuration/single/import

    1. Field Storage -
      field.storage.feeds_feed.field_calendar_owner_uid.yml
    2. Feed Type -   
      feeds.feed_type.outlook_to_calendar_restrictions.yml
    3. Field -   
      field.feeds_feed.outlook_to_calendar_restrictions.field_calendar_owner_uid.yml
    4. Entity Form Display -
       feeds_feed.outlook_to_calendar_restrictions.default.yml
    MUST do above in order 
	
Once Imported (for whatever reason) Open the Feed Type and Save (creating individual feeds will cause an error unless this is done) and check that the form display is correct


5. Create specific Feed for your Calendar using the Feed type (above) "Outlook To Calendar Restrictions" .
  There should be a custom field to enter the User ID for the Calendar Owner
  You could use a calendar with actual subjects, but by default I use the Free/Busy Calendar (for Privacy)

  Jon Goldberg's Main Free Busy Calendar:

  https://outlook.live.com/owa/calendar/0237ed64-81c9-46c1-9297-c6ee45656538/2283c4af-6235-4759-9c06-cd6d0dae9c2a/cid-0C34A841728FC775/calendar.ics

  If something goes wrong use drush entity:delete node --bundle=calendar_restriction_instances

6. webform_ics_handler - Needed if you want to Create an ICS file which can be added to a meeting request
   a. Creates an ICS file from the above webform which can be sent with an email. 
   b. This is saved in the Public Files directory under a folder called ICS.  Probably not the most secure so should be looked at to put in Private Directory 

7. meeting_request_webform_handler - Used to Create a Request to the owner of the calendar for a meeting.
  This module does 2 things: 
   a. Creates a Webform which allows you to make a meeting request.
   b. A custom Webform Handler that checks that the date and time are really available based on the Restrictions and existing appointments. Creates the Appointment if it does. 
   c. NOTE: There seems to be some sort of Bug in Webform.  The webform gets created correctly but for some reason the time fields won't work unless you view the Source of the Webform (under build) and then Save it.
   d. If Wanted Make the Calendar Owner Field "Private" so it is not shown to anonymous users
   e. Other (standard) handlers can them be used to send the meeting request with any additional configurations needed.
   f. Installs a Drupal View ( Public Calendar -- views.view.bookable_calendar.yml) to open the Webform.  
      1) This is a Clickable calendar where people can click on a day and that will open up a webform to create an appointment for that day.
      2) User will not Show up in the Select List of available calendars unless they have at least 1 time restriction set 
      3) There may be conflict with the standard Captcha form in that it thinks the calendar is a form.  You could probably remove this through JavaScript but I changed what Captcha I was using
      4) To get the view to work properly, there standard fullcalendar_view must be manually patched (I could not get this to work in a module) 
         i.) There is a directory called fullcalendar_view_update
         ii.) from that directory run the script apply_patches.sh  
         iii.) if things do not work check the location of your fullcalendar_view module. Patching in Drupal is very tricky and pick on directory locations
         iv.) These patches make some changes to the standard Full Calendar View Module that allow the creation of an appointment via a Webform . It might be possible to do as overrides but I could not make it work.
      5) The system is setup to be integrated with CiviCRM (e.g. the Name you select in the calendar comes from the CiviCRM Record). While this is not really necessary, it adds a great deal of functionality. And, as I am on the advisory council for CiviCRM, it is my duty to encourage its use.  Or feel free to rewrite the code without it. It would be quite simple to make the User Name selection use the User.ID.  None of the base modules rely on CiviCRM, just the Meeting Request form and Calendar.
   g. The Calendar Owner and Ics Fields are set to "Private" which means if you are testing as Admin you will see them, but they should not show for normal users. 


