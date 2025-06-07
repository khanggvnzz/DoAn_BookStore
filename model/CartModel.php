<?php


class Cart
{
    public $cart_id;
    public $user_id;
    public $id;
    public $quantity;

    public function __construct($data = [])
    {
        if (!empty($data)) {
            $this->cart_id = isset($data['cart_id']) ? $data['cart_id'] : null;
            $this->user_id = isset($data['user_id']) ? $data['user_id'] : null;
            $this->id = isset($data['id']) ? $data['id'] : null;
            $this->quantity = isset($data['quantity']) ? $data['quantity'] : 0;
        }
    }

    public function toArray()
    {
        return [
            'cart_id' => $this->cart_id,
            'user_id' => $this->user_id,
            'id' => $this->id,
            'quantity' => $this->quantity
        ];
    }
}
?>