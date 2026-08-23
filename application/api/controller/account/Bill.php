<?php

namespace app\api\controller\account;

use app\common\controller\AccountApi;
use app\common\library\account\BillService;
use think\Exception;

/**
 * 账单流水
 */
class Bill extends AccountApi
{
    protected $noNeedLogin = [];

    /**
     * 批量入账（事务；超量走 Redis 异步队列）
     *
     * POST:
     *  category_id, quantity, items=[{param_id,quantity?}], bill_date?, remark?, client_req_id?, async?
     */
    public function batch()
    {
        if (!$this->request->isPost()) {
            $this->error('请使用 POST');
        }

        $items = $this->request->post('items/a', []);
        $json = null;
        // 兼容 JSON body
        if (!$items) {
            $raw = $this->request->getContent();
            if ($raw) {
                $json = json_decode($raw, true);
                if (is_array($json) && !empty($json['items'])) {
                    $items = $json['items'];
                }
            }
        }

        $payload = [
            'category_id'   => (int)$this->request->post('category_id', 0),
            'quantity'      => $this->request->post('quantity', '1'),
            'items'         => $items,
            'bill_date'     => $this->request->post('bill_date', ''),
            'remark'        => $this->request->post('remark', ''),
            'client_req_id' => $this->request->post('client_req_id', ''),
            'async'         => (int)$this->request->post('async', 0),
        ];
        if (is_array($json)) {
            if (!$payload['category_id'] && !empty($json['category_id'])) {
                $payload['category_id'] = (int)$json['category_id'];
            }
            if (isset($json['quantity'])) {
                $payload['quantity'] = $json['quantity'];
            }
            if (!empty($json['bill_date'])) {
                $payload['bill_date'] = $json['bill_date'];
            }
            if (isset($json['remark'])) {
                $payload['remark'] = $json['remark'];
            }
            if (!empty($json['client_req_id'])) {
                $payload['client_req_id'] = $json['client_req_id'];
            }
            if (!empty($json['async'])) {
                $payload['async'] = 1;
            }
        }

        try {
            $result = BillService::batchEntry($this->auth->id(), $payload);
            $this->success('入账成功', $result);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        } catch (\Exception $e) {
            $this->error($e->getMessage());
        }
    }

    /**
     * 我的账单（强制 user_id 隔离）
     */
    public function index()
    {
        $data = BillService::listByUser($this->auth->id(), [
            'bill_ym'     => $this->request->param('bill_ym', date('Ym')),
            'category_id' => $this->request->param('category_id', 0),
            'page'        => $this->request->param('page', 1),
            'limit'       => $this->request->param('limit', 20),
        ]);
        $this->success('', $data);
    }
}
