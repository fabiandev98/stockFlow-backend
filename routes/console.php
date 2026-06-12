<?php

Schedule::command('sanctum:prune-expired --hours=0')->daily();
