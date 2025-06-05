class ReportFacade
{
    private CsvGenerator $csvGenerator;
    private CsvSenderInterface $csvSender;

    public function __construct(CsvGenerator $csvGenerator, CsvSenderInterface $csvSender)
    {
        $this->csvGenerator = $csvGenerator;
        $this->csvSender = $csvSender;
    }

    public function generateAndSendReport(): bool
    {
        $filePath = $this->csvGenerator->generateFromEntities();
        return $this->csvSender->sendCSV($filePath);
    }
 public function transformJsonToXml(string $jsonData): string
    {
        // Decode JSON data
        $data = json_decode($jsonData, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON data provided');
        }

        // Extract the special field
        if (!isset($data['super_specail_field'])) {
            throw new \InvalidArgumentException('Required field "super_specail_field" not found in JSON data');
        }

        // Create XML structure
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><response></response>');
        $xml->addChild('external_data', $data['super_specail_field']);

        return $xml->asXML();
    }
}
