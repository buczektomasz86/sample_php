<?php
/**
 *    use App\Helpers\SetDeviceRelayStatus;
 *
 *    try{
 *      $setDeviceRelayStatus = new SetDeviceRelayStatus($device_id);
 *      $setDeviceRelayStatus->getStatus();
 *    } catch(\Exception $e){
 *      // manage of exception code ...
 *    }
 */

namespace App\Helpers;

use App\DeviceControl;
use Exception;

class SetDeviceRelayStatus
{
    private $device_id;
    private $device_control;

    public function __construct(int $device_id)
    {
        $this->device_id = $device_id;
        $this->device_control = DeviceControl::where('device_id', $this->device_id)->first();
        if (!$this->device_control) {
            throw new Exception('Nie znaleziono ustawień sterowania dla urządzenia.');
        }
    }

    /**
     * Determine if the device should be enabled/disable.
     *
     * @return int
     */
    public function getStatus(): int
    {
        $device_control = $this->device_control;
        if ($device_control->enable == 0) {
            return 0;
        }

        if ($device_control->work_hour_from == 'manualne' || $device_control->work_hour_to == 'manualne') {
            return 1;
        }

        $date_now = date('Y-m-d H:i:s');
        $date_from = date("Y-m-d $device_control->work_hour_from");
        $date_to = date("Y-m-d $device_control->work_hour_to");

        $comparison_work_hours = $date_to <=> $date_from;
        if ($comparison_work_hours == 1) {
            if ($date_from <= $date_now && $date_to >= $date_now) {
                return 1;
            }

            return 0;
        } else if ($comparison_work_hours == 0) {

            return 1;
        } else if ($comparison_work_hours == -1) {
            if ($date_from >= $date_now && $date_to <= $date_now) {

                return 0;
            }

            return 1;
        }
    }
}
