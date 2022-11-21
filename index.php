<?php

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;

require 'vendor/autoload.php';

class Main
{
    private string $jwtToken;
    private string $clientGuid;
    private string $baseUrl;
    private Client $client;
    /**
     * @var string[]
     */
    private array $headers;
    private string $clientProductGuid;

    public function __construct()
    {
        $dotEnv = Dotenv\Dotenv::createImmutable(__DIR__);
        $dotEnv->safeLoad();

        $this->jwtToken = $_ENV['JWT_TOKEN'];
        $this->clientGuid = $_ENV['CLIENT_GUID'];
        $this->baseUrl = $_ENV['TAZ_URL'];
        $this->clientProductGuid = 'a04cc4e3-7af8-4f0f-a4c7-c4ca77ca220c'; // Tenant Product // can be obtained using allProducts function

        $this->client = new Client();
        $this->headers = [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->jwtToken}"
        ];
    }
    
    public function allProducts()
    {
        $request = new Request('GET', $this->baseUrl . '/clients/' . $this->clientGuid . '/products', $this->headers);
        $res = $this->client->sendAsync($request)->wait();
        dump(json_decode($res->getBody()));
    }

    public function createApplicantThatUsesQuickApp(string $firstName, string $lastName, string $email): string
    {
        $data = [
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'textingEnabled' => false, // true if you provide the phoneNumber
            'phoneNumber' => null,
        ];
        $body = json_encode($data);
        $request = new Request('POST', "{$this->baseUrl}/clients/{$this->clientGuid}/applicants", $this->headers, $body);
        $res = $this->client->sendAsync($request)->wait();
        $response = json_decode($res->getBody());
        dump($response);
        return $response->applicantGuid;
    }

    public function submitOrder(string $applicantGuid): string
    {
        $data = [
            "applicantGuid" => $applicantGuid,
            "clientProductGuid" => $this->clientProductGuid,
            "useQuickApp" => true,
            "externalIdentifier" => "guid-from-your-system", // the id on your system to cross-reference
            "quickappNotifyApplicants" => true, // send the notification right away
            "certifyPermissiblePurpose" => true
        ];
        $body = json_encode($data);
        $request = new Request('POST', "{$this->baseUrl}/clients/{$this->clientGuid}/orders", $this->headers, $body);
        $res = $this->client->sendAsync($request)->wait();
        $response = json_decode($res->getBody());
        dump($response);
        return $response->orderGuid;
    }

    public function checkOrderStatus(string $orderGuid): string
    {
        $request = new Request("GET", "{$this->baseUrl}/clients/{$this->clientGuid}/orders/{$orderGuid}/status", $this->headers);
        $res = $this->client->sendAsync($request)->wait();
        $response = json_decode($res->getBody());
        dump($response);
        return $response->orderDetail->status;
    }
    
    public function getPdf(string $orderGuid)
    {
        $request = new Request("GET", "{$this->baseUrl}/clients/{$this->clientGuid}/orders/{$orderGuid}/resultsPdf", $this->headers);
        $res = $this->client->sendAsync($request)->wait();
        $response = json_decode($res->getBody());
        dump($response);
    }

    public function allOrders()
    {
        $request = new Request("GET", "{$this->baseUrl}/clients/{$this->clientGuid}/orders?page=0&size=20", $this->headers);
        $res = $this->client->sendAsync($request)->wait();
        $response = json_decode($res->getBody());
        dump($response);
    }

    public function allClients()
    {
        $request = new Request("GET", "{$this->baseUrl}/clients?page=0&size=20", $this->headers);
        $res = $this->client->sendAsync($request)->wait();
        $response = json_decode($res->getBody());
        dump($response);
    }
}

$main = new Main();

$applicantGuid = $main->createApplicantThatUsesQuickApp("Marcelo", "Andrade", "mandrade@smithcarson.com");

$orderGuid = $main->submitOrder($applicantGuid);

// $orderGuid = "a394f387-4887-4f27-9d4f-4ce3542e1aeb";

$status = $main->checkOrderStatus($orderGuid);
// app-pending (APPLICANT_PENDING) means the user have not opened the quickapp form yet
// app-ready  (APPLICANT_READY) means the user have submitted all the info in quickapp
// wait or listen for webhook call until status is 'complete'
// there are other statuses: https://docs.developer.tazworks.com/?version=latest#16c2bff6-501c-432b-a89d-19141f277fc6

// Misc calls
// $main->getPdf($orderGuid); die();
// $main->allOrders(); die();
// $main->allProducts(); die();
// $main->allClients(); die();