<?php

class HR4EmployeeClient
{
    private $apiUrl = 'https://hr4.microfinancial-1.com/allemployees';
    private $apiKey = 'b24e8778f104db434adedd4342e94d39cee6d0668ec595dc6f02c739c522b57a'; // Replace with actual key
    private $timeout = 15;

    public function __construct($apiKey = null)
    {
        if ($apiKey) {
            $this->apiKey = $apiKey;
        }
    }

    /**
     * Fetch all employees from HR4 API
     * @return array
     * @throws Exception
     */
    public function fetchAllEmployees()
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'X-api-key: ' . $this->apiKey
        ]);

        // For development/self-signed certificates if needed
        // curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            throw new Exception("HR4 API Connection Error: " . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception("HR4 API returned non-200 status: " . $httpCode . ". Response: " . $response);
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("HR4 API returned invalid JSON: " . json_last_error_msg());
        }

        return $data;
    }
}
