<?php

namespace app\admin\command;

use app\common\library\account\BillService;
use think\console\Command;
use think\console\Input;
use think\console\input\Option;
use think\console\Output;

/**
 * 预创建未来月份账单分表，避免首单建表抖动
 * php think account:bill-prepare --months=2
 */
class AccountBillPrepare extends Command
{
    protected function configure()
    {
        $this->setName('account:bill-prepare')
            ->addOption('months', null, Option::VALUE_OPTIONAL, '从当前月开始预建几个月', 2)
            ->addOption('start', null, Option::VALUE_OPTIONAL, '起始账期YYYYMM，默认当前月', '')
            ->setDescription('Precreate account bill shard tables');
    }

    protected function execute(Input $input, Output $output)
    {
        $months = max(1, (int)$input->getOption('months'));
        $start = trim((string)$input->getOption('start'));
        $startYm = preg_match('/^\d{6}$/', $start) ? (int)$start : null;

        $series = BillService::buildMonthSeries($months, $startYm);
        $done = BillService::ensureMonthTables($series);

        $output->writeln('prepared: ' . implode(',', $done));
    }
}
