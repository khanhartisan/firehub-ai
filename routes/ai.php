<?php

use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp/app', \App\Mcp\Servers\AppServer::class);