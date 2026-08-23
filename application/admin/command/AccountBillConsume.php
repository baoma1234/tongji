<?php

namespace app\admin\command;

use app\common\library\account\BillService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 异步入账队列消费者
 * php think account:bill-consume --max=100 --loop=1
 */
class AccountBillConsume extends Command
{
    protected function configure()
    {
        $this->setName('account:bill-consume')
            ->addOption('max', null, Option::VALUE_OPTIONAL, '每次最多消费条数', 50)
            ->addOption('loop', null, Option::VALUE_OPTIONAL, '是否循环(1=是)', 0)
            ->addOption('sleep', null, Option::VALUE_OPTIONAL, '空闲休眠秒', 1)
            ->setDescription('Consume async account bill queue from Redis');
    }

    protected function execute(Input $input, Output $output)
    {
        $max = (int)$input->getOption('max');
        $loop = (int)$input->getOption('loop');
        $sleep = max(1, (int)$input->getOption('sleep'));

        do {
            $done = BillService::consumeQueue($max);
            if ($done > 0) {
                $output->writeln('[' . date('Y-m-d H:i:s') . "] consumed={$done}");
            } elseif ($loop) {
                sleep($sleep);
            }
        } while ($loop);

        if (!$loop) {
            $output->writeln("done, consumed={$done}");
        }
    }
}
