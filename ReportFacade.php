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
}
