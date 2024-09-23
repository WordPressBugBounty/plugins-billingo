<?php

class BillingoOrder extends WC_Order
{
    public function get_tax_location_modified($args = [])
    {
        return $this->get_tax_location($args);
    }
}
