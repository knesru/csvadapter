use App\Service\Interfaces\CsvSenderInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ApiCsvSender implements CsvSenderInterface
{
    private HttpClientInterface $httpClient;
    private string $apiUrl;

    public function __construct(HttpClientInterface $httpClient, string $apiUrl)
    {
        $this->httpClient = $httpClient;
        $this->apiUrl = $apiUrl;
    }

    public function sendCSV(string $filePath, array $metadata = []): bool
    {
        try {
            $response = $this->httpClient->request('POST', $this->apiUrl, [
                'headers' => [
                    'Content-Type' => 'multipart/form-data',
                ],
                'body' => [
                    'file' => fopen($filePath, 'r'),
                    'metadata' => json_encode($metadata),
                ],
            ]);

            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            // Логирование ошибки
            return false;
        }
    }
}
