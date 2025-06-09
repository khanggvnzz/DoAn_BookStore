<?php

class HistoryOrder
{
    private $history_id;
    private $user_id;
    private $product;
    private $cost;
    private $created_at;
    private $pay_method;
    private $note;
    private $order_id;
    private $voucher_id;

    public function __construct(
        $history_id = null,
        $user_id = null,
        $product = null,
        $cost = null,
        $created_at = null,
        $pay_method = null,
        $note = null,
        $order_id = null,
        $voucher_id = null
    ) {
        $this->history_id = $history_id;
        $this->user_id = $user_id;
        $this->product = $product;
        $this->cost = $cost;
        $this->created_at = $created_at;
        $this->pay_method = $pay_method;
        $this->note = $note;
        $this->order_id = $order_id;
        $this->voucher_id = $voucher_id;
    }

    // Getters
    public function getHistoryId()
    {
        return $this->history_id;
    }

    public function getUserId()
    {
        return $this->user_id;
    }

    public function getProduct()
    {
        return $this->product;
    }

    public function getCost()
    {
        return $this->cost;
    }

    public function getCreatedAt()
    {
        return $this->created_at;
    }

    public function getPayMethod()
    {
        return $this->pay_method;
    }

    public function getNote()
    {
        return $this->note;
    }

    public function getOrderId()
    {
        return $this->order_id;
    }

    public function getVoucherId()
    {
        return $this->voucher_id;
    }

    // Setters
    public function setHistoryId($history_id)
    {
        $this->history_id = $history_id;
    }

    public function setUserId($user_id)
    {
        $this->user_id = $user_id;
    }

    public function setProduct($product)
    {
        $this->product = $product;
    }

    public function setCost($cost)
    {
        $this->cost = $cost;
    }

    public function setCreatedAt($created_at)
    {
        $this->created_at = $created_at;
    }

    public function setPayMethod($pay_method)
    {
        $this->pay_method = $pay_method;
    }

    public function setNote($note)
    {
        $this->note = $note;
    }

    public function setOrderId($order_id)
    {
        $this->order_id = $order_id;
    }

    public function setVoucherId($voucher_id)
    {
        $this->voucher_id = $voucher_id;
    }

    // toArray function
    public function toArray()
    {
        return [
            'history_id' => $this->history_id,
            'user_id' => $this->user_id,
            'product' => $this->product,
            'cost' => $this->cost,
            'created_at' => $this->created_at,
            'pay_method' => $this->pay_method,
            'note' => $this->note,
            'order_id' => $this->order_id,
            'voucher_id' => $this->voucher_id
        ];
    }
}
?>