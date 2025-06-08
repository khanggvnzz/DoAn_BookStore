<?php

class Order
{
    private $order_id;
    private $user_id;
    private $product;
    private $cost;
    private $created_at;
    private $pay_method;
    private $note;

    public function __construct($order_id = null, $user_id = null, $product = null, $cost = null, $created_at = null, $pay_method = null, $note = null)
    {
        $this->order_id = $order_id;
        $this->user_id = $user_id;
        $this->product = $product;
        $this->cost = $cost;
        $this->created_at = $created_at;
        $this->pay_method = $pay_method;
        $this->note = $note;
    }

    public function toArray()
    {
        return [
            'order_id' => $this->order_id,
            'user_id' => $this->user_id,
            'product' => $this->product,
            'cost' => $this->cost,
            'created_at' => $this->created_at,
            'pay_method' => $this->pay_method,
            'note' => $this->note
        ];
    }
}

?>