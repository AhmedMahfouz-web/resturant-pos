<?php

namespace App\Observers;

use App\Events\TableEvents;
use App\Models\Table;

class TableObserver
{

    public function updated(Table $table): void
    {
        event(new TableEvents($table));
    }
}
