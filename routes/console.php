<?php

Schedule::command('sanctum:prune-expired --hours=0')->daily();
Schedule::command('inventory:expire-batches')->dailyAt('00:05');
