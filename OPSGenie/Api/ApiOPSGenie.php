<?php

namespace OPSGenie\Api;

use \Exception;

/**
 * Class ApiOPSGenie
 * @package OPSGenie\Api
 * @author Dominick Makome <dominick.makome@ufirstgroup.com>
 */
class ApiOPSGenie
{
    /**
     * @var string
     */
    private $sendFromEmail;

    /**
     * @var string
     */
    private $sendFromName;

    /**
     * @var string
     */
    private $applicationEnv;

    /**
     * @var string
     */
    private static $apiUrl = 'https://api.opsgenie.com/v1/json/alert';

    /**
     * @var string
     */
    private static $supportEMAIL = 'support.39394.52f671ce12b894a2@helpscout.net';

    /**
     * ApiOPSGenie constructor.
     * @param string $sendFromEmail
     * @param string $sendFromName
     */
    public function __construct($sendFromEmail, $sendFromName)
    {
        $this->sendFromEmail = $sendFromEmail;
        $this->sendFromName = $sendFromName;
        $this->applicationEnv = getenv('APPLICATION_ENV');
    }

    /**
     * @param string $subject
     * @param string $body
     *
     * @return array ($success, $error_code, $message)
     */
    public static function notifyHelpScout($subject, $body)
    {
        // save mandrill sandbox daily quota
        if ('live' !== getenv('APPLICATION_ENV')) {
            return ['status' => 'not-live', 'error_code' => 0, 'message' => '',];
        }
        return self::sendEmailNotification(self::$supportEMAIL, $subject, $body);
    }

    /**
     * @param string $subject
     * @param string $body
     *
     * @return array
     */
    public function notifyOpsGenie($subject, $body)
    {
        $subject = "SEND FROM $this->sendFromName:  $this->applicationEnv / " . $subject;
        try {
            $apiKey = $this->getOpsgenieApiKey();
            $requestParams = [
                'apiKey' => $apiKey,
                'message' => $subject,
                'description' => $body,
                'recipients' => 'devops_escalation',
            ];
            $jsonBody = json_encode($requestParams);
            $ch = curl_init(self::$apiUrl);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $result = curl_exec($ch);
            curl_close($ch);
            $resultArray = @json_decode($result, true);

            $response = ['status' => 'success', 'error_code' => 0, 'message' => '',];

            if (!(isset($resultArray['status']) && 'successful' === $resultArray['status'])) {
                $response['status'] = 'failed';
                $response['error_code'] = -1;
                $response['message'] = 'Opsgenie API alert failed';
            }
        } catch (Exception $e) {
            $response['status'] = 'failed';
            $response['error_code'] = -1;
            $response['message'] = $e->getMessage();
        }

        return $response;
    }

    /**
     * @param string $name
     *
     * @return void
     */
    public function sendOpsGenieHeartbeat($name)
    {
        shell_exec("/usr/local/bin/opsgenie_send_heartbeat.sh '$name'");
    }

    /**
     * @return mixed
     *
     * @throws Exception
     */
    private function getOpsgenieApiKey()
    {
        if (!is_readable('/usr/local/lib/opsgenie/api_key.php')) {
            throw new Exception('Opsgenie API key file does not exist');
        }
        include_once '/usr/local/lib/opsgenie/api_key.php';
        if (!isset($OPSGENIE_API_KEY)) {
            throw new Exception('Opsgenie API key not defined in file');
        }
        return $OPSGENIE_API_KEY;
    }
    /**
     * @return array
     */
    private static function sendEmailNotification($to, $subject, $body)
    {
        try {
            mail($to, $subject, $body);
            $response = ['status' => 'success', 'error_code' => 0, 'message' => '',];
        }
        catch (Exception $e) {
            $response = ['status' => 'failed', 'error_code' => -1, 'message' => $e->getMessage(),];
        }

        return $response;
    }

}
