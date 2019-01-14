<?php
/*
 *    use App\Helpers\SendAlertsByEmail;
 *
 *    try {
 *      $sendAlertsByEmail = new SendAlertsByEmail();
 *    } catch (\Exception $e) {
 *      echo $e->getMessage();
 *      Log::error(__class__ . ' ' . $e->getTraceAsString());
 *    }
 */
namespace App\Helpers;

use App\Device;
use App\DeviceAlert;
use App\Helpers\SendEmailByPHPMailer;
use App\MailServer;
use Exception;

class SendAlertsByEmail
{
    protected $alerts_to_send_collection;
    protected $alerts_to_send_collection_filtered_by_type_liquid_level;
    protected $alerts_to_send_collection_filtered_by_type_temperature;
   
    private $type;

    public function __construct()
    {
        $this->alerts_to_send_collection = DeviceAlert::where([
            ['is_set_email_to_sent', 1],
            ['is_email_has_been_sent', 0],
            ['is_email_has_been_omitted', 0],
        ])
            ->latest()
            ->get();

        if ($this->alerts_to_send_collection->isEmpty()) {
            throw new Exception('Brak alertów do wysłania wiadomościa email.');
        }

        $this->sendAlertsByEmail();
    }

    protected function sendAlertsByEmail()
    {
        $this->alerts_to_send_collection_filtered_by_type_liquid_level = $this->filterAlertsCollectionByType(1);
        $alerts_to_send_collection_filtered_by_type_liquid_level_grouped_by_device = $this->alerts_to_send_collection_filtered_by_type_liquid_level->groupBy('device_id');
        $this->checkCollectionByAlertsToSend($alerts_to_send_collection_filtered_by_type_liquid_level_grouped_by_device);

        $this->alerts_to_send_collection_filtered_by_type_temperature = $this->filterAlertsCollectionByType(2);
        $alerts_to_send_collection_filtered_by_type_temperature_grouped_by_device = $this->alerts_to_send_collection_filtered_by_type_temperature->groupBy('device_id');
        $this->checkCollectionByAlertsToSend($alerts_to_send_collection_filtered_by_type_temperature_grouped_by_device);
    }

    protected function checkCollectionByAlertsToSend($collection_grouped_by_device)
    {
        foreach ($collection_grouped_by_device as $key => $alerts) {
            if ($this->isAlertShouldBeSend($alerts[0])) {
                $this->sendAlertByEmail($alerts[0]);
                $alerts[0]->update(array("is_email_has_been_sent" => 1));
                foreach ($alerts as $key => $value) {
                    if ($key == 0) {
                        continue;
                    }

                    $value->update(array("is_email_has_been_omitted" => 1));
                }
            }
        }
    }

    protected function isAlertShouldBeSend($alert)
    {
        $last_alert_send_by_email = DeviceAlert::where([
            ['device_id', $alert->device_id],
            ['is_set_email_to_sent', 1],
            ['is_email_has_been_sent', 1],
            ['type', $alert->type],
        ])
            ->latest()
            ->first();

        if (!$last_alert_send_by_email || ($last_alert_send_by_email && $last_alert_send_by_email->level != $alert->level)) {
            return true;
        }

        return false;
    }

    protected function sendAlertByEmail($alert)
    {
        $device = Device::find($alert->device_id);
        if (!$device->alertsMainSetting) {
            throw new Exception('Brak ustawień alertów dla urządzenia o ID = ' . $alert->device_id);
        }

        if ($device->alertsMainSetting->email) {
            self::sendEmail($device->alertsMainSetting->email, $device, $alert);
        }
    }

    protected function filterAlertsCollectionByType($type)
    {
        if (!$type) {
            throw new Exception('Nieprawidłowy paramertr type');
        }

        $this->type = $type;

        return $this->alerts_to_send_collection->filter(function ($value, $key) {
            return $value->type == $this->type;
        });
    }

    private static function sendEmail($email_to, $device, $alert)
    {
        $mailServer = MailServer::first();
        $send_email = (new SendEmailByPHPMailer())
            ->setFromEmail($mailServer->email)
            ->setFromAlias($mailServer->alias)
            ->setToEmail($email_to)
            ->setToAlias($email_to)
            ->setSubject($alert->name . ' - ' . $device->device_tank_name)
            ->setBody($alert->name . ' - ' . $device->device_tank_name);
        if ($send_email->sendEmail()) {
            return true;
        }

        return false;
    }
}
