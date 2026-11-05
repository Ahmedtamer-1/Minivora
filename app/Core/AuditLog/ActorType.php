<?php

namespace App\Core\AuditLog;

enum ActorType: string
{
    case User = 'user';
    case Agent = 'agent';   // an AI agent acting for a user or on a schedule (FR-AI-10)
    case System = 'system'; // jobs, webhooks, the platform itself
}
