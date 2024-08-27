<?php

namespace Drupal\cal_restrict_webform_handler\Plugin\WebformHandler;


use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;

use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;

use Drupal\webform\Plugin\WebformHandlerBase;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;

use Drupal\user\Entity\User;
use Drupal\user\UserInterface;

/**
 * Webform submission handler.
 *
 * @WebformHandler(
 *   id = "cal_restrict_webform_handler",
 *   label = @Translation("Calendar Restriction Webform Handler"),
 *   category = @Translation("Custom"),
 *   description = @Translation("Handles custom webform submissions."),
 *   cardinality = \Drupal\webform\Plugin\WebformHandlerInterface::CARDINALITY_UNLIMITED,
 *   results = \Drupal\webform\Plugin\WebformHandlerInterface::RESULTS_PROCESSED,
 * )
 */

 class CalRestrictWebformHandler extends WebformHandlerBase {

  /**
   * {@inheritdoc}
   */

  public function defaultConfiguration() {
    return [
      'custom_setting' => 'default_value',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    $form['custom_setting'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Custom Setting'),
      '#default_value' => $this->configuration['custom_setting'],
      '#description' => $this->t('A custom setting for the handler.'),
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);
    $this->configuration['custom_setting'] = $form_state->getValue('custom_setting');
  }

  /**
   * {@inheritdoc}
   */

  public function validateForm(array &$form, FormStateInterface $form_state, WebformSubmissionInterface $webform_submission) {
    $current_date = (string) date('Y-m-d');

    $data = $webform_submission->getData();
    $webform_keys = array_keys($data);
    $calendar_restrictions_fields=$data['calendar_restrictions_by_time'];
    $my_info = json_encode($calendar_restrictions_fields);
    $stop_validation = FALSE;
    $my_row_id = 0;
    // Fields in Webform calendar_restrictions_fields are "dow","start_time","end_time","duration_days",
    // Seperate fields are for  "calendar_instance_owner", "webform_submission_id"
    //need to check each that start_time is < end_time as no way to do though standard methods

    foreach ($calendar_restrictions_fields as $calendar_restriction) {
      $updated_start_time = $current_date . ' ' . $calendar_restriction['start_time'];
      $updated_start_time_datevalue = strtotime($updated_start_time);
      $updated_end_time = $current_date . ' ' . $calendar_restriction['end_time'];
      $updated_end_time_datevalue = strtotime($updated_end_time);
      $my_row_id++;
      if ($updated_start_time_datevalue > $updated_end_time_datevalue) {
        $my_info = 'The start time in row. '.$my_row_id. ' for '. $calendar_restriction['dow'] . ' is greater than the end time. Please fix before submitting.';
        $stop_validation = TRUE;
        break;
      }
    }
    if ($stop_validation == TRUE) {
      $form_state->setErrorByName('start_time', $this->t('The Form Cannot be Validated. '  . $my_info));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(WebformSubmissionInterface $webform_submission) {
    parent::preSave($webform_submission);

    // The following is for the nodes for the individual restrictions based on the days out.   
    // Delete all node restrictions instances associated with ID of the user and with the ical UID set to "Custom Restriction".
    $data = $webform_submission->getData();
    $webform_keys = array_keys($data);
    $content_type = 'calendar_restriction_instances';
    $calendar_instance_owner_webform = $data['calendar_restriction_owner'];
    if (NodeType::load($content_type)) {     
      $node_storage = $this->entityTypeManager->getStorage('node');
      $deletenodequery = $node_storage->getQuery()
        ->condition('type', $content_type)
        ->condition('field_ical_calendar_owner', $calendar_instance_owner_webform)
        ->condition('field_ical_record_uid', 'Custom Restriction')
        ->accessCheck(FALSE);
      $nids = $deletenodequery->execute();

        // Load and delete nodes.
      if (!empty($nids)) {
        $nodes = $node_storage->loadMultiple($nids);
        foreach ($nodes as $node) {
          $node->delete();
        }
        $mymessage = 'Deleted '. count($nids) . ' nodes.';
      }
      else {
        //Set Message Status
        $mymessage = 'No nodes to delete.';
      }
    }  
    //$mymessage = 'Submission Owner:'. $calendar_instance_owner . json_encode($nids);
    $this->messenger()->addWarning($this->t($mymessage), TRUE); 
   }

  /**
   * {@inheritdoc}
   */
  public function postSave(WebformSubmissionInterface $webform_submission, $update = TRUE) {
    parent::postSave($webform_submission, $update);
    //Custom post-save logic here.
    //$this->debug(__FUNCTION__);
     
    $database = \Drupal\Core\Database\Database::getConnection();
    $sql_file_dir = getcwd().'/'.\Drupal::service('extension.list.module')->getPath('cal_restrict_webform_handler').'/src/Plugin/WebformHandler/';

    $seconds_in_day=86400;
    $content_type = 'calendar_restriction_instances';

    //Get the value from the submissions

    $data = $webform_submission->getData();
    $calendar_restrictions_fields=$data['calendar_restrictions_by_time'];
    // Fields in Webform calendar_restrictions_fields are "dow","start_time","end_time","duration_days",
    // Seperate fields are for  "calendar_instance_owner", "webform_submission_id"

    //Set items that are the same for all records
    $connection = Database::getConnection();
    $id=null;
    $calendar_instance_owner = $data['calendar_restriction_owner'];
    $calendar_instance_owner_tz = $data['calendar_restriction_time_zone'];
    $submission_id = $webform_submission->id();
    $webform_id = $webform_submission->getWebform()->id();

    //First Delete all records related to the submission ID. 

    $delete_exising_submissions_sql = file_get_contents($sql_file_dir.'delete_existing_submissions.sql');

    //Due to the problems with passing quotes I generally try to keep them out of files which makes it hard to use traditional parameters, so I just force a FIND and replace for a string in the SQL

    $delete_exising_submissions_sql = str_replace("MY_OWNER_ID",$calendar_instance_owner,$delete_exising_submissions_sql);
    $delete_existing_records = $database->query($delete_exising_submissions_sql);

    //Loop thorugh each Record in the webform and write to the table for calendar restrictions

    foreach ($calendar_restrictions_fields as $calendar_restriction) {
      $dow = $calendar_restriction['dow'];
      $start_time = $calendar_restriction['start_time'];
      $end_time = $calendar_restriction['end_time'];
      $duration_days = $calendar_restriction['duration_days'];

      $fields = [
        'id' => $id, //Auto Increment so null should be good
        'dow' => $dow,
        'start_time' => $start_time,
        'end_time' => $end_time,
        'duration_days' => $duration_days,
        'calendar_instance_owner' => $calendar_instance_owner,
        'calendar_instance_time_zone' => $calendar_instance_owner_tz,
        'webform_submission_id' => $submission_id,
        'webform_id' => $webform_id,
      ];
  
      // Insert the record.
      $connection->merge('calendar_restrictions')
        ->key(['id' => $id])
        ->fields($fields)
        ->execute();
      }

    //Now Create the individual nodes for the restrictions. We use nodes (rather than a custom entity) so that they better fit work with views, Drupal security, and the problem that FEEDS will create a node great but not an Entity.
    // Fields in the Table calendar_restrictions are "id","dow","start_time","end_time","duration_days", "calendar_instance_owner", "webform_submission_id"

    //Make sure that Node Type exists
    if (NodeType::load($content_type)) { 
      //We can create the complete list of instances for the restrictions
      $exising_restrictions_sql = file_get_contents($sql_file_dir.'owner_restriction_instances.sql');
      $exising_restrictions_sql = str_replace("MY_OWNER_ID",$calendar_instance_owner,$exising_restrictions_sql);
      $query_existing_records = $database->query($exising_restrictions_sql);
      //$owner_tz = new \DateTimeZone($calendar_instance_owner_tz);
      //Note the for loop can be less than 1000 because in no world will you ever have 1000 restrictions set up 
      for ($current_record = 0; $current_record <= 1000; $current_record++) {
        // Do something
        try {
          // Try to get the next record in the array
          $query_result_record = $query_existing_records->fetchAssoc();  //fetches next record (first time gets the first record)
          echo "Processed item: $item\n";
        } catch (Exception $e) {
          echo "No More Records: " . $e->getMessage() . "\n";
          break; // Exit the loop on failure
        } 
        $current_dow = $query_result_record['dow'];
        $current_start_time = (string) $query_result_record['start_time'];
        $current_end_time = (string) $query_result_record['end_time'];
        $current_duration = $query_result_record['duration_days'];

        //Using Nodes for Insert

        // This variable will be added in each loop
        $current_date = (string) date('Y-m-d');
        //Replace Time in current Date 
        $updated_start_time = $current_date . ' ' . $current_start_time;
        $updated_start_time_UTC = new \DateTime($updated_start_time, new \DateTimeZone($calendar_instance_owner_tz));
        $updated_end_time = $current_date . ' ' . $current_end_time;
        $updated_end_time_UTC = new \DateTime($updated_end_time, new \DateTimeZone($calendar_instance_owner_tz));
        $start_time = strtotime($updated_start_time);
        if ($start_time == false) {
          $my_message = 'Invalid time format:'.$updated_start_time.'Of Type'.gettype($updated_start_time);
          break;
        }
        else {
          //DO First DOW before Loop through new dates so only rows will be added if the DOW of the added day equals the restriction
          $updated_start_time_dow = date('l', $start_time);
          for ($day_out = 0; $day_out <= $current_duration; $day_out++) {
            if ($updated_start_time_dow==$current_dow) {
              //The resriction is for the current day in the loop
              $nodetitle = (string) $calendar_instance_owner.'|'.$updated_start_time.'-'.$updated_end_time.'-'.$calendar_instance_owner_tz;
              $node = Node::create([
                'type' => $content_type, // The content type machine name.
                'title' => $nodetitle,
                'field_ical_calendar_owner' => $calendar_instance_owner,
                'field_ical_timezone' => $calendar_instance_owner_tz,               
                'field_ical_dtstart' => $updated_start_time,
                'field_ical_dtstart_utc_stamp' => $updated_start_time_UTC->format('U'),
                'field_ical_dtend' => $updated_end_time,
                'field_ical_dtend_utc_stamp' => $updated_end_time_UTC->format('U'),
                'field_ical_record_uid' => 'Custom Restriction',
                'uid' => 1, // The user ID of the admin as the author - Could change to special owner of Calendars.
                'status' => 1, // 1 means published.
                'promote' => 0, // 0 means do not promote to the front page.
                'sticky' => 0, // 0 means do not make sticky.
              ]);             
              // Save the node.
              $node->save();
           }
            //skip to the next day
            $new_start_time = strtotime($updated_start_time) + $seconds_in_day;
            $updated_start_time = date('Y-m-d\TH:i:s', $new_start_time);
            $updated_start_time_dow = date('l', $new_start_time);
            $updated_start_time_UTC = $updated_start_time_UTC->modify('+1 day');
            $new_end_time = strtotime($updated_end_time) + $seconds_in_day;
            $updated_end_time = date('Y-m-d\TH:i:s', $new_end_time);
            $updated_end_time_UTC = $updated_end_time_UTC->modify('+1 day');
            $my_message = 'Records in List: ' . $count_all_records . ' DOW From Restriction: '. $current_dow . 'DOW From Start time: '. $updated_start_time_dow .   ' New Date'.$updated_start_time.'-'.$updated_end_time;
          }
        }
      }
    }  
    else {
      $my_message = 'Restriction Table Does Not Exist';
    }
    $this->messenger()->addWarning($this->t($my_message), TRUE);
  }

  protected function debug($method_name, $context1 = NULL) {
    if (!empty($this->configuration['debug'])) {
      $t_args = [
        '@id' => $this->getHandlerId(),
        '@class_name' => get_class($this),
        '@method_name' => $method_name,
        '@context1' => $context1,
      ];
      $this->messenger()->addWarning($this->t('Invoked @id: @class_name:@method_name @context1', $t_args), TRUE);
    }
  }
}