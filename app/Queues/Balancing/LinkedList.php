<?php

namespace App\Queues\Balancing;

class LinkedList
{
    public $head;
    private $tail;

    public function append(Node $node): void
    {
        if (is_null($this->head)) {
            $this->head = $node;
            $this->tail = $node;
        } else {
            $this->tail->next = $node;
            $this->tail = $node;
        }
    }

    public function pop(): Node
    {
        $node = $this->head;
        $this->head = $node->next;
        $node->next = null;

        return $node;
    }
}
