<?php

namespace Talleu\TriggerMapping\Tests\Application\Entity;

use Doctrine\ORM\Mapping as ORM;
use Talleu\TriggerMapping\Attribute\Trigger;

#[ORM\Entity]
#[Trigger(name: 'trg_make_test_mysql', on: ['UPDATE'], when: 'AFTER', scope: 'ROW')]
#[Trigger(name: 'trg_make_test_mysql', on: ['UPDATE'], when: 'AFTER', scope: 'ROW')]
#[Trigger(name: 'trg_make_test_mysql', on: ['UPDATE'], when: 'AFTER', scope: 'ROW')]
#[Trigger(name: 'trg_make_test_mysql', on: ['UPDATE'], when: 'AFTER', scope: 'ROW')]
#[Trigger(name: 'trg_make_test_mysql', on: ['UPDATE'], when: 'AFTER', scope: 'ROW')]
#[Trigger(name: 'trg_make_test_postgresql', function: 'func_test', on: ['INSERT', 'UPDATE'], when: 'AFTER', scope: 'ROW')]
#[Trigger(name: 'trg_make_test_postgresql', function: 'func_test', on: ['INSERT', 'UPDATE'], when: 'AFTER', scope: 'ROW')]
class NoTriggerEntity
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;
}