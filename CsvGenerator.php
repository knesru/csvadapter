use App\Repository\OrderRepository;
use App\Repository\UserRepository;

class CsvGenerator
{
    private OrderRepository $orderRepository;
    private UserRepository $userRepository;

    public function __construct(OrderRepository $orderRepository, UserRepository $userRepository)
    {
        $this->orderRepository = $orderRepository;
        $this->userRepository = $userRepository;
    }

    public function generateFromEntities(): string
    {
        // 1. Получаем данные
        $users = $this->userRepository->findActiveUsers();
        $orders = $this->orderRepository->findRecentOrders();

        // 2. Генерируем CSV (пример упрощён)
        $csvData = "User,Order\n";
        foreach ($users as $user) {
            foreach ($orders as $order) {
                if ($order->getUser() === $user) {
                    $csvData .= sprintf("%s,%s\n", $user->getEmail(), $order->getId());
                }
            }
        }

        // 3. Сохраняем во временный файл
        $filePath = sys_get_temp_dir() . '/report_' . date('Y-m-d') . '.csv';
        file_put_contents($filePath, $csvData);

        return $filePath;
    }
}
