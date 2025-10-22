<?php

namespace App;

final class Events
{
    public const string PROJECT_CREATE = 'project.create';
    public const string PROJECT_EDIT = 'project.edit';
    public const string PROJECT_DELETE = 'project.delete';
    public const string USER_CREATE = 'user.create';
    public const string USER_EDIT = 'user.edit';
    public const string USER_DELETE = 'user.delete';
    public const string CONVERSATION_CREATE = 'conversation.create';
    public const string CONVERSATION_EDIT = 'conversation.edit';
    public const string CONVERSATION_DELETE = 'conversation.delete';
    public const string AGENT_ASK = 'agent.ask';
    public const string AGENT_RESPONSE = 'agent.response';
}
