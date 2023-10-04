<?php

namespace Drupal\migrate_silfi\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Transforms the D7 date field and time field values into a smart date value.
 *
 * @MigrateProcessPlugin(
 *   id = "date_time_to_smart_date"
 * )
 */
class DateTimeToSmartDate extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    // Since the smart date field is a multi property field, this
    // gets only the sub property - i.e. end_value, duration, etc.
    $subProperty = explode('/', $destination_property);
    $subProperty = $subProperty[1];

    $dateFieldValue = $this->getFieldValue($row, 'src_start_datetime');
    $timeFieldValueEnd = $this->getFieldValue($row, 'src_end_datetime');

    // // This field is reversed for whatever reason. True = *not* full day event
    // // False = *is* full day event. To avoid confusion, flip it.
    // $fullDayEventValue = (bool) $this->getFieldValue($row, 'field_full_day_event');
    // $isFullDayEvent = !$fullDayEventValue;

    $startTimestamp = 0;
    $endTimestamp = 0;

    /*
     * Since some of the fields are derived from others and the
     * calculations aren't heavy duty, go ahead and always
     * calculate all the fields and just return what we need.
     */
    if ($dateFieldValue) {
      if (!empty($dateFieldValue)) {
        $startTimestamp = $this->getTimestampFromDate($dateFieldValue);

        // Default the end date to match the start date
        // since worst case scenario we calculate it if set.
        $endTimestamp = $startTimestamp;
      }

      if (!empty($dateFieldValueEnd) && ($dateFieldValueEnd !== $dateFieldValue)) {
        $endTimestamp = $this->getTimestampFromDate($dateFieldValueEnd);
      }
    }

    // if ($timeFieldValue) {
    //   // Since the startTimestamp and endTimestamp timestamps are based on
    //   // midnight, and the D7 timeField value is the number of
    //   // seconds since midnight, we should be able to just add the
    //   // timeFieldValue to the corresponding timestamps.
    //   if (!empty($timeFieldValue['value'])) {
    //     $startTimestamp += $timeFieldValue['value'];
    //   }

    //   if (!empty($timeFieldValue['value2'])) {
    //     $endTimestamp += $timeFieldValue['value2'];
    //   }
    //   elseif (!empty($timeFieldValue['value'])) {
    //     $endTimestamp += $timeFieldValue['value'];
    //   }
    // }

    // if ($isFullDayEvent) {
    //   $endTimestamp = $this->convertTimeStampToFullDay($endTimestamp);
    // }

    // Calculate the duration by diffing the start and end date
    // and convert to minutes (timestamp is in seconds)
    $duration = ($endTimestamp - $startTimestamp) / 60;

    $smartDateFieldValues = [
      'value' => $startTimestamp,
      'end_value' => $endTimestamp,
      'duration' => $duration,
    ];

    $fieldValue = $this->validate($subProperty, $smartDateFieldValues[$subProperty]);

    return $fieldValue;
  }

  /**
   * Helper to get the specified field value from the source data.
   *
   * Also this will potentially clean up the value
   * (extract the value from nested arrays, etc.).
   *
   * @param Drupal\migrate\Row $row
   *   Migrate row.
   * @param string $fieldName
   *   Fieldname.
   *
   * @return any
   *   Field value.
   */
  private function getFieldValue(Row $row, string $fieldName) {
    $fieldValue = NULL;
    $sourceFieldValue = $row->getSource()[$fieldName];

    if (!empty($sourceFieldValue)) {
      $fieldValue = $sourceFieldValue;
    }

    if (is_array($fieldValue) && (count($fieldValue) === 1)) {
      $fieldValue = array_values($fieldValue)[0];
    }

    if (is_array($fieldValue) && (count($fieldValue) === 1) && isset($fieldValue['value'])) {
      $fieldValue = $fieldValue['value'];
    }

    return $fieldValue;
  }

  /**
   * Helper function to get the unix timestamp value from a given date string.
   *
   * @param string $date
   *   Date.
   *
   * @return int
   *   Timestamp.
   */
  private function getTimestampFromDate(string $date): int {
    // Since the time is stored in a separate field in D7,
    // ignore any time information from the date field.
    $timestamp = explode(' ', $date);
    $timestamp = strtotime($timestamp[0]);

    return $timestamp;
  }

  /**
   * Helper to convert a timestamp to the smart date all day format.
   *
   * @param int $timestamp
   *   Timestamp.
   *
   * @return int
   *   All day Timestamp.
   */
  private function convertTimeStampToFullDay(int $timestamp): int {
    // If this is a full day event, we need to set the ending timestamp
    // to be at 11:59:00pm on the last day.
    $fullDayEventEndTime = "11:59:00pm";
    $date = date('Y-m-d', $timestamp);
    $date .= " {$fullDayEventEndTime}";
    $timestamp = strtotime($date);

    return $timestamp;
  }

  /**
   * Helper function to do some basic validation on the generated data.
   *
   * @param string $propertyName
   *   Property name.
   * @param int $propertyValue
   *   Property value.
   *
   * @return any
   *   Property value if validated or NULL.
   */
  private function validate(string $propertyName, int $propertyValue) {
    if ($propertyValue < 0) {
      $errorMessage = "DateTimeToSmartDate->validate: {$propertyName} shouldn't be less than 0 (is {$propertyValue}).";
      $this->log($errorMessage);
      $this->throwPluginException($errorMessage);
      return NULL;
    }

    return $propertyValue;
  }

}
