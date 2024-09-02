<?php

namespace Drupal\meeting_request_webform_handler\Plugin\WebformHandler;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Database\Database;
use Drupal\Core\Database\Connection;  
use Drupal\Core\Database\Schema;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Form\FormStateInterface;
use Drupal\node\Entity\Node;
use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformSubmissionInterface;

use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form submission handler.
 *
 * @WebformHandler(
 *   id = "meeting_request_validation_handler",
 *   label = @Translation("Meeting Request Webform Handler"),
 *   category = @Translation("Validation"),
 *   description = @Translation("Webform handler to validate that the time of a Meeting is not restricted."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */
class MeetingRequestValidationHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    // Retrieve the submitted start time from the webform submission.
    //fields in this form are subject, requested_date, start_time, end_time, requestor_email, calendard_restriction_owner

   
    $data = $webform_submission->getData();
    $owner_tz = $data['owner_timezone'];
    $updated_start_time = $data['requested_date'] . ' ' . $data['start_time'];
    $updated_start_time_database_tz = new DrupalDateTime($updated_start_time, new \DateTimeZone($owner_tz));
    $updated_start_time_database = $updated_start_time_database_tz->format('Y-m-d\TH:i:s');
    

    $updated_end_time = $data['requested_date'] . ' ' . $data['end_time'];
    $updated_end_time_datevalue = strtotime($updated_end_time);
    $updated_end_time_database_tz = new DrupalDateTime($updated_end_time, new \DateTimeZone($owner_tz));
    $updated_end_time_database = $updated_end_time_database_tz->format('Y-m-d\TH:i:s');
  
    $calendar_instance_owner = $data['calendar_restriction_owner'];
  
    $content_type = 'calendar_restriction_instances';
    $field_one = 'field_ical_dtstart_utc_stamp';
    $field_two = 'field_ical_dtend_utc_stamp';
    $field_cal_owner = 'field_ical_calendar_owner';

    $value_start = $updated_start_time_database_tz->format('U');
    $start_node_query = \Drupal::entityQuery('node')
      ->condition('type', $content_type)
      ->condition($field_cal_owner, $calendar_instance_owner)
      ->condition($field_one, $value_start, '<=')
      ->condition($field_two, $value_start, '>=')
      ->accessCheck(FALSE);
    // Execute the query and load the nodes.
    $nids_start = $start_node_query->execute();
    
    $value_end = $updated_end_time_database_tz->format('U');
    $end_node_query = \Drupal::entityQuery('node')
      ->condition('type', $content_type)
      ->condition($field_cal_owner, $calendar_instance_owner)
      ->condition($field_one, $value_end, '<=')
      ->condition($field_two, $value_end, '>=')
      ->accessCheck(FALSE);
    // Execute the query and load the nodes.
    $nids_end = $end_node_query->execute();  

    // Check if Start_time before End Time and check  If any items are there (e.g. the result set is not null), then there is a problem and it is not a valid meeting time 
    
    //if ($data['end_time']<=$data['start_time']) {
      // However, since this is Computed now this should never Give an Error
    //  $my_message='Start Time must be before End Time.';
      // Cancel the validation
    //  $form_state->setErrorByName('start_time', $this->t($my_message));
    //}
    if (!empty($nids_start)||!empty($nids_end)) {
      //Since there is a result it is not valid
      $my_message='There is a conflict with the date and time chosen. Look at the calendar and choose an alternate time.';
      // Cancel the validation
      $form_state->setErrorByName('start_time', $this->t($my_message));
    }
    else {
      $my_message='There no conflict.';
      //go ahead and process
    }
  }
}