<?php

namespace App\Services\Tools\Contracts;

use App\Models\Conversation;

interface NeedsConversationContext
{
    public function setConversation(Conversation $conversation): void;
}
