use App\Service\CsvGenerator;
use App\Service\Interfaces\CsvSenderInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class SendCsvCommand extends Command
{
    protected static $defaultName = 'app:send-csv';
    private CsvGenerator $csvGenerator;
    private CsvSenderInterface $csvSender;

    public function __construct(CsvGenerator $csvGenerator, CsvSenderInterface $csvSender)
    {
        parent::__construct();
        $this->csvGenerator = $csvGenerator;
        $this->csvSender = $csvSender;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Генерация CSV
        $filePath = $this->csvGenerator->generateFromEntities();
        
        // Отправка
        if ($this->csvSender->sendCSV($filePath)) {
            $output->writeln('CSV отправлен!');
            unlink($filePath); // Удаляем временный файл
            return Command::SUCCESS;
        }

        $output->writeln('<error>Ошибка!</error>');
        return Command::FAILURE;
    }
}
<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ExternalApiService
{
    private const API_BASE_URL = 'https://api.example.com'; // Replace with actual API URL
    private const CREATE_APPLICATION_ENDPOINT = '/boba-1.0/rpc/v2/create-application';
    private const APPLICATION_STATUS_SHORT_ENDPOINT = '/application-status-short';
    private const APPLICATION_STATUS_ENDPOINT = '/application-status';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * Creates a new application via external API
     *
     * @param string $id Application ID
     * @param string $text Application text
     * @param array<string> $tags List of tags
     * @return string UUID of created application
     * @throws \RuntimeException When API request fails or response is invalid
     */
    public function createApplication(string $id, string $text, array $tags): string
    {
        $xml = $this->buildCreateApplicationXml($id, $text, $tags);
        
        try {
            $this->logger->info('Sending create application request', [
                'id' => $id,
                'tags' => $tags
            ]);

            $response = $this->httpClient->request('POST', self::API_BASE_URL . self::CREATE_APPLICATION_ENDPOINT, [
                'headers' => [
                    'Content-Type' => 'application/xml',
                ],
                'body' => $xml,
            ]);

            $content = $response->getContent();
            $this->logger->debug('Create application response', ['response' => $content]);

            $xmlResponse = new \SimpleXMLElement($content);
            
            if ((string)$xmlResponse->success !== 'true') {
                throw new \RuntimeException(sprintf(
                    'API error: %s (code: %s)',
                    (string)$xmlResponse->message ?? 'Unknown error',
                    (string)$xmlResponse->code ?? 'N/A'
                ));
            }

            return (string)$xmlResponse->data;
        } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->logger->error('Failed to create application', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            throw new \RuntimeException('Failed to create application: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            $this->logger->error('Invalid response format', [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            throw new \RuntimeException('Invalid response format: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Gets short application status
     *
     * @param string $applicationId Application ID
     * @param bool $returnIntermediateState Whether to return intermediate state
     * @return array{status: string, callName: string, finalDecision: string, isTerminal: string, text: string}
     * @throws \RuntimeException When API request fails or response is invalid
     */
    public function applicationStatusShort(string $applicationId, bool $returnIntermediateState = false): array
    {
        return $this->getApplicationStatus($applicationId, $returnIntermediateState, self::APPLICATION_STATUS_SHORT_ENDPOINT);
    }

    /**
     * Gets full application status
     *
     * @param string $applicationId Application ID
     * @param bool $returnIntermediateState Whether to return intermediate state
     * @return array{status: string, callName: string, finalDecision: string, isTerminal: string, text: string}
     * @throws \RuntimeException When API request fails or response is invalid
     */
    public function applicationStatus(string $applicationId, bool $returnIntermediateState = false): array
    {
        return $this->getApplicationStatus($applicationId, $returnIntermediateState, self::APPLICATION_STATUS_ENDPOINT);
    }

    /**
     * Common method for getting application status
     *
     * @param string $applicationId Application ID
     * @param bool $returnIntermediateState Whether to return intermediate state
     * @param string $endpoint API endpoint
     * @return array{status: string, callName: string, finalDecision: string, isTerminal: string, text: string}
     * @throws \RuntimeException When API request fails or response is invalid
     */
    private function getApplicationStatus(string $applicationId, bool $returnIntermediateState, string $endpoint): array
    {
        try {
            $this->logger->info('Getting application status', [
                'applicationId' => $applicationId,
                'endpoint' => $endpoint
            ]);

            $response = $this->httpClient->request('GET', self::API_BASE_URL . $endpoint, [
                'query' => [
                    'applicationId' => $applicationId,
                    'returnIntermediateState' => $returnIntermediateState ? 'true' : 'false',
                ],
            ]);

            $content = $response->getContent();
            $this->logger->debug('Application status response', ['response' => $content]);

            $xmlResponse = new \SimpleXMLElement($content);

            return [
                'status' => (string)$xmlResponse->status,
                'callName' => (string)$xmlResponse->callName,
                'finalDecision' => (string)$xmlResponse->finalDecision,
                'isTerminal' => (string)$xmlResponse->isTerminal,
                'text' => (string)$xmlResponse->text,
            ];
        } catch (TransportExceptionInterface|ClientExceptionInterface|ServerExceptionInterface|RedirectionExceptionInterface $e) {
            $this->logger->error('Failed to get application status', [
                'error' => $e->getMessage(),
                'applicationId' => $applicationId
            ]);
            throw new \RuntimeException('Failed to get application status: ' . $e->getMessage(), 0, $e);
        } catch (\Exception $e) {
            $this->logger->error('Invalid response format', [
                'error' => $e->getMessage(),
                'applicationId' => $applicationId
            ]);
            throw new \RuntimeException('Invalid response format: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Builds XML for create application request
     *
     * @param string $id Application ID
     * @param string $text Application text
     * @param array<string> $tags List of tags
     * @return string XML string
     */
    private function buildCreateApplicationXml(string $id, string $text, array $tags): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><request></request>');
        
        $xml->addChild('callName', 'START');
        $xml->addChild('applicationId', $id);
        $xml->addChild('text', $text);
        
        $tagList = $xml->addChild('tagList');
        foreach ($tags as $tag) {
            $tagList->addChild('tag', $tag);
        }

        return $xml->asXML();
    }
} 
