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

use DateTimeZone as PhpDateTimeZone;
use Symfony\Component\HttpFoundation\Request;

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
    $submission_tz = $data['owner_timezone'] ?? 'UTC';
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

    // Retrieve the ID of the previous submission, if exists.
    $submission_id = !empty($submission_ids) ? reset($submission_ids) : NULL;

    //$dateStart = '2024-08-04 10:30:45';
    //$dateEnd = '2024-08-04 12:30:45';

    $dateStart = $meeting_date.' '.$start;
    $dateEnd = $meeting_date.' '.$end;
    $format = 'Y-m-d H:i:s';
   
    $timeZone = new \DateTimeZone($submission_tz);
    $isUTC = True;

    $updated_start_time_UTC = new EluceoDateTime(new \DateTime($dateStart), $isUTC, $timeZone);
    $updated_end_time_UTC = new EluceoDateTime(new \DateTime($dateEnd), $isUTC, $timeZone);

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

      //$testfile = 'http://localhost/system/files/webform/meeting_request/146/1723802400_1723806000.ics';

      $data['ics_file'] = $file_id;
      // Update the submission data.
      $webform_submission->setData($data);
      //. 'File Name'. $my_file_URI . 'ID:' . $file_id . 'All: '.json_encode($data)
      // Notify the user of success.
      \Drupal::messenger()->addMessage(t('ics file successfully saved to: ' . $ics_path  ));
    } 
    catch (\Exception $e) {
      // Notify the user of failure.
      \Drupal::messenger()->addMessage(t('Unable to Save the File ' . $ics_path ));
    }
  }
}