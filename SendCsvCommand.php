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
