<?php
class Voucher
{
    private $voucher_id;
    private $code;
    private $name;
    private $description;
    private $min_order_amount;
    private $discount_percent;
    private $quantity;
    private $used_count;
    private $is_active;
    private $created_at;
    private $expires_at;

    public function __construct(
        $voucher_id = null,
        $code = null,
        $name = null,
        $description = null,
        $min_order_amount = null,
        $discount_percent = null,
        $quantity = null,
        $used_count = 0,
        $is_active = true,
        $created_at = null,
        $expires_at = null
    ) {
        $this->voucher_id = $voucher_id;
        $this->code = $code;
        $this->name = $name;
        $this->description = $description;
        $this->min_order_amount = $min_order_amount;
        $this->discount_percent = $discount_percent;
        $this->quantity = $quantity;
        $this->used_count = $used_count;
        $this->is_active = $is_active;
        $this->created_at = $created_at;
        $this->expires_at = $expires_at;
    }

    /**
     * Convert voucher object to array
     * 
     * @return array Associative array containing all voucher properties
     */
    public function toArray(): array
    {
        return [
            'voucher_id' => $this->voucher_id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'min_order_amount' => $this->min_order_amount,
            'discount_percent' => $this->discount_percent,
            'quantity' => $this->quantity,
            'used_count' => $this->used_count,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at
        ];
    }
}

?>