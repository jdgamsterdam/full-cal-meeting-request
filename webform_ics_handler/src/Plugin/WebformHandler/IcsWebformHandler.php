<?php

namespace Drupal\webform_ics_handler\Plugin\WebformHandler;

//use Drupal\Core\File;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\DrupalKernel;
use Drupal\Core\Form\FormStateInterface;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;
use Drupal\webform\Entity\WebformSubmission;

use Drupal\file\Entity\File;

use Eluceo\iCal\Domain\Entity\Calendar;
use Eluceo\iCal\Domain\Entity\Event;
use Eluceo\iCal\Domain\Entity\TimeZone;

use Eluceo\iCal\Domain\ValueObject\Date;
use Eluceo\iCal\Domain\ValueObject\DateTime as EluceoDateTime;
use Eluceo\iCal\Domain\ValueObject\DateTimeImmutable as EluceoDateTimeImmutable;
use Eluceo\iCal\Domain\ValueObject\Location;
use Eluceo\iCal\Domain\ValueObject\TimeSpan;

use Eluceo\iCal\Presentation\Factory\CalendarFactory;
use Eluceo\iCal\Presentation\Factory\DateTimeFactory;

use Symfony\Component\HttpFoundation\Request;

use DateTime;
use DateTimeZone;

/**
 * Webform submission handler to create an ICS file.
 *
 * @WebformHandler(
 *   id = "ics_webform_handler",
 *   label = @Translation("ICS Webform Handler"),
 *   category = @Translation("Custom"),
 *   description = @Translation("Creates an ICS file upon webform submission."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_SINGLE,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class IcsWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $data = $webform_submission->getData();
    // Extract data from the webform submission.
    $summary = $data['summary'] ?? 'No Summary';
    $description = $data['description'] ?? 'No Description';
    $meeting_date = $data['requested_date'];
    $start = $data['start_time'];
    $end = $data['end_time'];
    $owner_tz = $data['owner_timezone'] ?? 'UTC';
    $location = $data['location'] ?? 'No Location';
    
    // This should work but for some reason online it does not maybe due to a database speed error
    //$submission_id = $webform_submission->id();

    //So do it the complicated way

    $webform_id = $webform_submission->getWebform()->id();

    // Get the previous Webform submission for this user, if any.
    $query = \Drupal::entityTypeManager()->getStorage('webform_submission')->getQuery()
      ->condition('webform_id', $webform_id)
      ->sort('created', 'DESC')
      ->accessCheck(FALSE)
      ->range(1, 1);  // Skip the current submission to get the previous one.
    $submission_ids = $query->execute();

    // Retrieve the ID of the previous submission, if exists.  Need to do it this way as it does not seem to be possible to get the potential ID of the current submiission
    $submission_id = !empty($submission_ids) ? reset($submission_ids) : 0;

    //Update the Current Submission_id to the current. Only really used for the file name so should cause a problem. But Probably should add something do deal with ics files not being overwritten 
    $submission_id = $submission_id + 1;

    //Date must be in the follwing format to work 
    //$format = 'Y-m-d H:i:s';
    //$dateStart = '2024-08-04 10:30:45';

    $dateStart = $meeting_date.' '.$start;  
    $dateEnd = $meeting_date.' '.$end;

    // Convert to UTC but with out Z 

    $dateStart_UTC = new DateTime($dateStart);
    // Convert to UTC timezone
    $dateStart_UTC->setTimezone(new DateTimeZone('UTC'));
    // Format the DateTime to UTC text
    $dateStart_UTC_Text = $dateStart_UTC->format('Ymd\THis\Z');

    $dateEnd_UTC = new DateTime($dateEnd);
    // Convert to UTC timezone
    $dateEnd_UTC->setTimezone(new DateTimeZone('UTC'));
    // Format the DateTime to UTC text
    $dateEnd_UTC_Text = $dateEnd_UTC->format('Ymd\THis\Z');

    
    $isUTC = True;
    //This is how it is with TZ but does not seem to work. $updated_start_time_UTC = new EluceoDateTime(new \DateTime($dateStart), $isUTC, $timeZone);
    $updated_start_time_UTC = new EluceoDateTime(new \DateTime($dateStart), $isUTC);
    $updated_end_time_UTC = new EluceoDateTime(new \DateTime($dateEnd), $isUTC);

    // Create the ICS event.
    $event = new Event();
    $event->setSummary($summary)
          ->setDescription($description)
          ->setLocation(new Location($location))
          ->setOccurrence(new TimeSpan($updated_start_time_UTC, $updated_end_time_UTC));

    $calendar = new \Eluceo\iCal\Domain\Entity\Calendar([$event]);
    $calendarFactory = new CalendarFactory();
    $calendarComponent = $calendarFactory->createCalendar($calendar);

    // Save the ICS file.
    $ics_content = (string) $calendarComponent;   
     
    //Since the iCal Module not playing nicely with Drupal "manually" replace the DTSTART and DTEND with the addition of the Time Zone and Get rid of the Z at the end so it is not UTC

    $START_W_TZ = "DTSTART;TZID=".$owner_tz.":";
    $END_W_TZ = "DTEND;TZID=".$owner_tz.":";
    
    //Add Time Zone
    $ics_content = str_replace("DTSTART:",$START_W_TZ,$ics_content);
    $ics_content = str_replace("DTEND:",$END_W_TZ,$ics_content);
   
    //Replace Start and End with Z (as it now has TimeZones)
    $ics_content = str_replace($dateStart_UTC_Text,rtrim($dateStart_UTC_Text, 'Z'),$ics_content);
    $ics_content = str_replace($dateEnd_UTC_Text,rtrim($dateEnd_UTC_Text, 'Z'),$ics_content);
  
    $ics_filename = $submission_id.'_'.$updated_start_time_UTC->getDateTime()->getTimestamp().'_'.$updated_end_time_UTC->getDateTime()->getTimestamp().'.ics';

    // Get the file system service.
    $file_system = \Drupal::service('file_system');

    // Define the path for the new 'ics' subdirectory.
    //$ics_directory = 'public://ics';
    $ics_directory = 'private://ics';

    // Prepare the directory (this will create it if it doesn't exist).
    $directory_created = $file_system->prepareDirectory($ics_directory, FileSystemInterface::CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);

    if (!$directory_created) {
      \Drupal::messenger()->addMessage(t("Failed to create the 'ics' directory. Please Check Permissions in the Public Directory"), 'error');
    }

    $ics_path = $ics_directory . '/' . $ics_filename;

    try {
      // Use the file system service to save the data to the file.
      
      \Drupal::service('file.repository')->writeData($ics_content, $ics_path, FileSystemInterface::EXISTS_REPLACE);
      
      // Load the file entity by URI.
      $my_file = \Drupal::service('file.repository')->loadByUri($ics_path);
      $my_file_URI = $my_file->getFileUri();
      $file_id = $my_file->id();


      $data['ics_file'] = $file_id;
      // Update the submission data.
      $webform_submission->setData($data);
      // Notify the user of success.
      \Drupal::messenger()->addMessage(t('ics file successfully saved to: ' . $ics_path  ));
    } 
    catch (\Exception $e) {
      // Notify the user of failure.
      \Drupal::messenger()->addMessage(t('Unable to Save the File ' . $ics_path ));
    }
  }
}