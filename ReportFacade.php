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

namespace App\Command;

use App\Service\DataTransformerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'app:json-to-xml-consumer',
    description: 'Consumes JSON files and transforms them to XML',
)]
class JsonToXmlConsumerCommand extends Command
{
    private const INPUT_DIR = 'var/queue/json';
    private const OUTPUT_DIR = 'var/queue/xml';
    private const PROCESSED_DIR = 'var/queue/processed';

    public function __construct(
        private readonly DataTransformerService $transformer,
        private readonly Filesystem $filesystem
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        // Ensure directories exist
        $this->ensureDirectoriesExist();

        while (true) {
            $finder = new Finder();
            $finder->files()
                ->in(self::INPUT_DIR)
                ->name('*.json');

            if ($finder->hasResults()) {
                foreach ($finder as $file) {
                    try {
                        $jsonContent = file_get_contents($file->getPathname());
                        $xmlContent = $this->transformer->transformJsonToXml($jsonContent);

                        // Generate output filename
                        $outputFilename = $file->getBasename('.json') . '.xml';
                        $outputPath = self::OUTPUT_DIR . '/' . $outputFilename;

                        // Save XML
                        file_put_contents($outputPath, $xmlContent);

                        // Move processed file
                        $this->filesystem->rename(
                            $file->getPathname(),
                            self::PROCESSED_DIR . '/' . $file->getBasename()
                        );

                        $io->success(sprintf('Processed file: %s', $file->getBasename()));
                    } catch (\Exception $e) {
                        $io->error(sprintf(
                            'Error processing file %s: %s',
                            $file->getBasename(),
                            $e->getMessage()
                        ));
                    }
                }
            }

            // Sleep for a short time to prevent CPU overuse
            sleep(1);
        }

        return Command::SUCCESS;
    }
}
