<?php

declare(strict_types=1);

namespace App\Integration\AxiLink\Dto;

class Application
{
    public function __construct(
        private readonly string $id,
        private readonly string $text,
        private readonly array $tags,
        private readonly ?string $applicationSource = null,
        private readonly ?string $applicationType = null,
        private readonly ?string $processingRequestType = null,
        private readonly ?bool $resubmissionFlag = false,
        private readonly ?string $applicationCrossReferenceId = null,
        private readonly ?int $strategySelectionRandomNumber = null,
        private readonly ?\DateTimeInterface $applicationDate = null,
        private readonly ?string $transactionId = null,
        private readonly ?\DateTimeInterface $timestamp = null,
        private readonly ?string $strategyVersion = null,
        private readonly ?string $debugLevel = null,
        private readonly ?bool $swDecision = false,
        private readonly ?string $submitterId = null,
        private readonly ?string $deliveryOptionCode = null,
        private readonly ?string $decisioningRequestType = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function getApplicationSource(): ?string
    {
        return $this->applicationSource;
    }

    public function getApplicationType(): ?string
    {
        return $this->applicationType;
    }

    public function getProcessingRequestType(): ?string
    {
        return $this->processingRequestType;
    }

    public function getResubmissionFlag(): ?bool
    {
        return $this->resubmissionFlag;
    }

    public function getApplicationCrossReferenceId(): ?string
    {
        return $this->applicationCrossReferenceId;
    }

    public function getStrategySelectionRandomNumber(): ?int
    {
        return $this->strategySelectionRandomNumber;
    }

    public function getApplicationDate(): ?\DateTimeInterface
    {
        return $this->applicationDate;
    }

    public function getTransactionId(): ?string
    {
        return $this->transactionId;
    }

    public function getTimestamp(): ?\DateTimeInterface
    {
        return $this->timestamp;
    }

    public function getStrategyVersion(): ?string
    {
        return $this->strategyVersion;
    }

    public function getDebugLevel(): ?string
    {
        return $this->debugLevel;
    }

    public function getSwDecision(): ?bool
    {
        return $this->swDecision;
    }

    public function getSubmitterId(): ?string
    {
        return $this->submitterId;
    }

    public function getDeliveryOptionCode(): ?string
    {
        return $this->deliveryOptionCode;
    }

    public function getDecisioningRequestType(): ?string
    {
        return $this->decisioningRequestType;
    }
} 
<?php

declare(strict_types=1);

namespace App\Integration\AxiLink\Dto;

class ApplicationResponse
{
    public function __construct(
        private readonly bool $success,
        private readonly string $message,
        private readonly ?string $code = null,
        private readonly ?string $data = null,
        private readonly ?MessageList $messageList = null,
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getData(): ?string
    {
        return $this->data;
    }

    public function getMessageList(): ?MessageList
    {
        return $this->messageList;
    }
} 
<?php

declare(strict_types=1);

namespace App\Integration\AxiLink\Dto;

class MessageList
{
    /**
     * @param Message[] $messages
     */
    public function __construct(
        private readonly array $messages,
        private readonly ?int $statusCode = null,
        private readonly ?string $statusDescription = null,
    ) {
    }

    /**
     * @return Message[]
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function getStatusCode(): ?int
    {
        return $this->statusCode;
    }

    public function getStatusDescription(): ?string
    {
        return $this->statusDescription;
    }
} 
<?php

declare(strict_types=1);

namespace App\Integration\AxiLink\Dto;

class Message
{
    public function __construct(
        private readonly ?string $description = null,
        private readonly ?string $detailedDescription = null,
        private readonly ?string $resolution = null,
        private readonly ?int $messageNumber = null,
        private readonly ?int $severityCode = null,
    ) {
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDetailedDescription(): ?string
    {
        return $this->detailedDescription;
    }

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function getMessageNumber(): ?int
    {
        return $this->messageNumber;
    }

    public function getSeverityCode(): ?int
    {
        return $this->severityCode;
    }
} 
<?php

declare(strict_types=1);

namespace App\Integration\AxiLink;

use App\Integration\AxiLink\Dto\Application;
use App\Integration\AxiLink\Dto\ApplicationResponse;
use App\Integration\AxiLink\Dto\Message;
use App\Integration\AxiLink\Dto\MessageList;
use Exception;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SimpleXMLElement;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AxiLinkApiClient implements AxilinkApiPort
{
    private const API_BASE_URL = 'https://api.axilink.com';

    private const CREATE_APPLICATION_ENDPOINT = '/axilink-1.0/rpc/v2/create-application';

    private const APPLICATION_STATUS_SHORT_ENDPOINT = '/application-status-short';

    private const APPLICATION_STATUS_ENDPOINT = '/application-status';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly string $host = '',
        private readonly string $endpoint = '',
        private readonly int $maxRetries = 1,
        private readonly int $minRetryInterval = 1,
    ) {
    }

    /**
     * @throws RuntimeException
     */
    public function createApplication(Application $application): ApplicationResponse
    {
        $xml = $this->buildCreateApplicationXml($application);

        try {
            $this->logger->info('Sending create application request', [
                'id' => $application->getId(),
                'tags' => $application->getTags(),
            ]);

            $response = $this->httpClient->request('POST', self::API_BASE_URL . self::CREATE_APPLICATION_ENDPOINT, [
                'headers' => [
                    'Content-Type' => 'application/xml',
                ],
                'body' => $xml,
            ]);

            $content = $response->getContent();
            $this->logger->debug('Create application response', ['response' => $content]);

            $xmlResponse = new SimpleXMLElement($content);

            $messageList = null;
            if (isset($xmlResponse->MessageList)) {
                $messages = [];
                foreach ($xmlResponse->MessageList->Message as $message) {
                    $messages[] = new Message(
                        description: (string) $message->Description,
                        detailedDescription: (string) $message->DetailedDescription,
                        resolution: (string) $message->Resolution,
                        messageNumber: (int) $message['MessageNumber'],
                        severityCode: (int) $message['SeverityCode']
                    );
                }
                $messageList = new MessageList(
                    messages: $messages,
                    statusCode: (int) $xmlResponse->MessageList['StatusCode'],
                    statusDescription: (string) $xmlResponse->MessageList['StatusDescription']
                );
            }

            return new ApplicationResponse(
                success: (string) $xmlResponse->success === 'true',
                message: (string) $xmlResponse->message,
                code: (string) $xmlResponse->code,
                data: (string) $xmlResponse->data,
                messageList: $messageList
            );
        } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->logger->error('Failed to create application', [
                'error' => $e->getMessage(),
                'id' => $application->getId(),
            ]);

            throw new RuntimeException('Failed to create application: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->logger->error('Invalid response format', [
                'error' => $e->getMessage(),
                'id' => $application->getId(),
            ]);

            throw new RuntimeException('Invalid response format: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @return array{status: string, callName: string, finalDecision: string, isTerminal: string, text: string}
     *
     * @throws RuntimeException
     */
    public function applicationStatusShort(string $applicationId, bool $returnIntermediateState = false): array
    {
        return $this->getApplicationStatus($applicationId, $returnIntermediateState, self::APPLICATION_STATUS_SHORT_ENDPOINT);
    }

    /**
     * @return array{status: string, callName: string, finalDecision: string, isTerminal: string, text: string}
     *
     * @throws RuntimeException
     */
    public function applicationStatus(string $applicationId, bool $returnIntermediateState = false): array
    {
        return $this->getApplicationStatus($applicationId, $returnIntermediateState, self::APPLICATION_STATUS_ENDPOINT);
    }

    /**
     * @return array{status: string, callName: string, finalDecision: string, isTerminal: string, text: string}
     *
     * @throws RuntimeException
     */
    private function getApplicationStatus(string $applicationId, bool $returnIntermediateState, string $endpoint): array
    {
        try {
            $this->logger->info('Getting application status', [
                'applicationId' => $applicationId,
                'endpoint' => $endpoint,
            ]);

            $response = $this->httpClient->request('GET', self::API_BASE_URL . $endpoint, [
                'query' => [
                    'applicationId' => $applicationId,
                    'returnIntermediateState' => $returnIntermediateState ? 'true' : 'false',
                ],
            ]);

            $content = $response->getContent();
            $this->logger->debug('Application status response', ['response' => $content]);

            $xmlResponse = new SimpleXMLElement($content);

            return [
                'status' => (string) $xmlResponse->status,
                'callName' => (string) $xmlResponse->callName,
                'finalDecision' => (string) $xmlResponse->finalDecision,
                'isTerminal' => (string) $xmlResponse->isTerminal,
                'text' => (string) $xmlResponse->text,
            ];
        } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->logger->error('Failed to get application status', [
                'error' => $e->getMessage(),
                'applicationId' => $applicationId,
            ]);

            throw new RuntimeException('Failed to get application status: ' . $e->getMessage(), 0, $e);
        } catch (Exception $e) {
            $this->logger->error('Invalid response format', [
                'error' => $e->getMessage(),
                'applicationId' => $applicationId,
            ]);

            throw new RuntimeException('Invalid response format: ' . $e->getMessage(), 0, $e);
        }
    }

    private function buildCreateApplicationXml(Application $application): string
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><request></request>');

        $xml->addChild('callName', 'START');
        $xml->addChild('applicationId', $application->getId());
        $xml->addChild('text', $application->getText());

        if ($application->getApplicationSource()) {
            $xml->addChild('applicationSource', $application->getApplicationSource());
        }

        if ($application->getApplicationType()) {
            $xml->addAttribute('ApplicationType', $application->getApplicationType());
        }

        if ($application->getProcessingRequestType()) {
            $xml->addAttribute('ProcessingRequestType', $application->getProcessingRequestType());
        }

        if ($application->getResubmissionFlag() !== null) {
            $xml->addAttribute('ResubmissionFlag', $application->getResubmissionFlag() ? 'true' : 'false');
        }

        if ($application->getApplicationCrossReferenceId()) {
            $xml->addAttribute('ApplicationCrossReferenceId', $application->getApplicationCrossReferenceId());
        }

        if ($application->getStrategySelectionRandomNumber() !== null) {
            $xml->addAttribute('StrategySelectionRandomNumber', (string) $application->getStrategySelectionRandomNumber());
        }

        if ($application->getApplicationDate()) {
            $xml->addAttribute('ApplicationDate', $application->getApplicationDate()->format('Y-m-d'));
        }

        if ($application->getTransactionId()) {
            $xml->addAttribute('TransactionId', $application->getTransactionId());
        }

        if ($application->getTimestamp()) {
            $xml->addAttribute('Timestamp', $application->getTimestamp()->format('Y-m-d\TH:i:s'));
        }

        if ($application->getStrategyVersion()) {
            $xml->addAttribute('StrategyVersion', $application->getStrategyVersion());
        }

        if ($application->getDebugLevel()) {
            $xml->addAttribute('DebugLevel', $application->getDebugLevel());
        }

        if ($application->getSwDecision() !== null) {
            $xml->addAttribute('SWDecision', $application->getSwDecision() ? 'true' : 'false');
        }

        if ($application->getSubmitterId()) {
            $xml->addAttribute('SubmitterId', $application->getSubmitterId());
        }

        if ($application->getDeliveryOptionCode()) {
            $xml->addAttribute('DeliveryOptionCode', $application->getDeliveryOptionCode());
        }

        if ($application->getDecisioningRequestType()) {
            $xml->addAttribute('DecisioningRequestType', $application->getDecisioningRequestType());
        }

        $tagList = $xml->addChild('tagList');
        foreach ($application->getTags() as $tag) {
            $tagList->addChild('tag', $tag);
        }

        return $xml->asXML();
    }

    public function sendData(array $data): array
    {
        $this->logger->info('Sending data to Axilink', ['data' => $data]);
        
        // Имитируем успешный ответ
        return [
            'status' => 'success',
            'message' => 'Data received successfully',
            'data' => $data
        ];
    }
}
<?php

declare(strict_types=1);

namespace App\Integration\AxiLink;

use App\Integration\AxiLink\Dto\Application;
use App\Integration\AxiLink\Dto\ApplicationResponse;

interface AxilinkApiPort
{
    /**
     * @throws \RuntimeException
     */
    public function createApplication(Application $application): ApplicationResponse;

    /**
     * @return array{status: string, callName: string, finalDecision: string, isTerminal: string, text: string}
     *
     * @throws \RuntimeException
     */
    public function applicationStatusShort(string $applicationId, bool $returnIntermediateState = false): array;

    /**
     * @return array{status: string, callName: string, finalDecision: string, isTerminal: string, text: string}
     *
     * @throws \RuntimeException
     */
    public function applicationStatus(string $applicationId, bool $returnIntermediateState = false): array;

    /**
     * @return array{status: string, message: string, data: array}
     */
    public function sendData(array $data): array;
}
<?php

declare(strict_types=1);

namespace App\Integration\AxiLink;

class DataTransformerService
{
    public function convertToEcofinXml(array $data): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><Application></Application>');

        // application
        if (isset($data['application'])) {
            $app = $data['application'];
            $xml->addChild('applicationId', (string)($app['applicationId'] ?? ''));
            $xml->addChild('amount', (string)($app['amount'] ?? ''));
            $xml->addChild('term', (string)($app['term'] ?? ''));
            $xml->addChild('firstApplicationDate', (string)($app['firstApplicationDate'] ?? ''));
            $xml->addChild('currentApplicationDate', (string)($app['currentApplicationDate'] ?? ''));
            $xml->addChild('personalDataUsageConsent', $app['personalDataUsageConsent'] ? 'true' : 'false');
            $xml->addChild('amountOriginal', (string)($app['amountOriginal'] ?? ''));
            $xml->addChild('insuranceConsentStatus', $app['insuranceConsentStatus'] ? 'true' : 'false');
        }

        // client -> applicant
        if (isset($data['client'])) {
            $client = $data['client'];
            $applicant = $xml->addChild('applicant');
            $applicant->addChild('firstName', $client['firstName'] ?? '');
            $applicant->addChild('lastName', $client['lastName'] ?? '');
            $applicant->addChild('middleName', $client['middleName'] ?? '');
            $applicant->addChild('sex', $client['sex'] ?? '');
            $applicant->addChild('dob', $client['dob'] ?? '');
            $applicant->addChild('mobilePhone', $client['mobilePhone'] ?? '');
            $applicant->addChild('email', $client['email'] ?? '');
            // паспорт
            if (isset($client['passport'])) {
                $passport = $applicant->addChild('passport');
                $passport->addChild('passportSerial', $client['passport']['passportSerial'] ?? '');
                $passport->addChild('passportNumber', $client['passport']['passportNumber'] ?? '');
                $passport->addChild('issuedDate', $client['passport']['issuedDate'] ?? '');
                $passport->addChild('divisionCode', $client['passport']['divisionCode'] ?? '');
                $passport->addChild('issuedBy', $client['passport']['issuedBy'] ?? '');
                $passport->addChild('expireDate', $client['passport']['expireDate'] ?? '');
            }
            // адреса
            if (isset($client['actualAddress'])) {
                $address = $applicant->addChild('actualAddress');
                $address->addChild('city', $client['actualAddress']['city'] ?? '');
                $address->addChild('house', $client['actualAddress']['house'] ?? '');
                $address->addChild('zip', $client['actualAddress']['zip'] ?? '');
                $address->addChild('apartment', $client['actualAddress']['apartment'] ?? '');
                $address->addChild('street', $client['actualAddress']['street'] ?? '');
            }
            if (isset($client['declaredAddress'])) {
                $address = $applicant->addChild('declaredAddress');
                $address->addChild('city', $client['declaredAddress']['city'] ?? '');
                $address->addChild('house', $client['declaredAddress']['house'] ?? '');
                $address->addChild('zip', $client['declaredAddress']['zip'] ?? '');
                $address->addChild('apartment', $client['declaredAddress']['apartment'] ?? '');
                $address->addChild('street', $client['declaredAddress']['street'] ?? '');
            }
        }

        // loanHistory
        if (isset($data['loanHistory'])) {
            $loanHistory = $xml->addChild('loanHistory');
            $lh = $data['loanHistory'];
            $loanHistory->addChild('issuedLoansReg', (string)($lh['issuedLoansReg'] ?? ''));
            $loanHistory->addChild('maxDpd', (string)($lh['maxDpd'] ?? ''));
            $loanHistory->addChild('daysPrevLoan', (string)($lh['daysPrevLoan'] ?? ''));
            $loanHistory->addChild('sumAllLoans', (string)($lh['sumAllLoans'] ?? ''));
            $loanHistory->addChild('lastLoanAmountOriginal', (string)($lh['lastLoanAmountOriginal'] ?? ''));
        }

        // riskAssessment
        if (isset($data['riskAssessment'])) {
            $risk = $xml->addChild('riskAssessment');
            $risk->addChild('clientInBlacklist', $data['riskAssessment']['clientInBlacklist'] ? 'true' : 'false');
        }

        // fraudDetection
        if (isset($data['fraudDetection'])) {
            $fraud = $xml->addChild('fraudDetection');
            $fraud->addChild('proxyUsed', $data['fraudDetection']['proxyUsed'] ? 'true' : 'false');
            $fraud->addChild('ipAddress', $data['fraudDetection']['ipAddress'] ?? '');
        }

        // crossProducts
        if (isset($data['crossProducts'])) {
            $cross = $xml->addChild('crossProducts');
            $cross->addChild('totalProductsSold', (string)($data['crossProducts']['totalProductsSold'] ?? ''));
        }

        return $xml->asXML();
    }
} 
