<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default AI Provider
    |--------------------------------------------------------------------------
    | Supported: "openai", "anthropic", "ollama", "gemini"
    */
    'default' => env('AI_PROVIDER', 'openai'),

    /*
    |--------------------------------------------------------------------------
    | AI Provider Connections
    |--------------------------------------------------------------------------
    */
    'connections' => [
        'openai' => [
            'driver' => 'openai',
            'api_key' => env('OPENAI_API_KEY'),
            'model' => env('OPENAI_MODEL', 'gpt-4o'),
        ],

        'anthropic' => [
            'driver' => 'anthropic',
            'api_key' => env('ANTHROPIC_API_KEY'),
            'model' => env('ANTHROPIC_MODEL', 'claude-opus-4-5'),
            'base_url' => 'https://api.anthropic.com/v1',
        ],

        'ollama' => [
            'driver' => 'ollama',
            'base_url' => env('OLLAMA_BASE_URL', 'http://localhost:11434/v1'),
            'model' => env('OLLAMA_MODEL', 'gemma4:26b'),
            // 'model' => env('OLLAMA_MODEL', 'qwen3.5:9b'),
        ],

        'ollama_remote' => [
            'driver' => 'ollama',
            'base_url' => env('OLLAMA_REMOTE_BASE_URL', 'http://your-server:11434/v1'),
            'model' => env('OLLAMA_REMOTE_MODEL', 'llama3.2'),
        ],

        'gemini' => [
            'driver' => 'gemini',
            'api_key' => env('GEMINI_API_KEY'),
            'model' => env('GEMINI_MODEL', 'gemini-2.0-flash'),
            'base_url' => 'https://generativelanguage.googleapis.com/v1beta',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Agent System Prompt
    |--------------------------------------------------------------------------
    */
    'system_prompt' => env('AI_SYSTEM_PROMPT', <<<'PROMPT'
You are a helpful office secretary assistant. You help manage tasks, emails, calendars,
browse the web, and automate workflows. You are efficient, concise, and proactive.
When you have tools available, use them to complete tasks rather than just describing how.

## Running learned skills
When the user asks you to perform a task and a matching learned skill is listed below:
1. Call read_skill with action=read and the skill's ID to get the full step-by-step instructions.
2. Execute each step in order using the appropriate browser/web/memory tools.
3. Report the outcome to the user when done.
If no skill matches, perform the task using your best judgment and available tools.

## Teaching new skills
- If the user asks you to "learn", "remember how to", "teach you", or "record" a new workflow,
  call start_recording with a concise skill name, then guide them step-by-step through the demonstration.
- When the user indicates the demonstration is complete (e.g. "that's it", "done", "save it"),
  call stop_recording to save the skill.
Never expose internal terms like "learn mode", "skill compilation", or tool names to the user.
PROMPT),

    /*
    |--------------------------------------------------------------------------
    | Learn Mode System Prompt Suffix
    |--------------------------------------------------------------------------
    */
    'learn_mode_system_prompt' => <<<'PROMPT'

You are currently recording the skill "{skill_name}".
Pay close attention to each step the user shows you. Ask clarifying questions when needed.
When you have everything you need, call stop_recording to save the skill.
PROMPT,

    /*
    |--------------------------------------------------------------------------
    | Tool Execution Limits
    |--------------------------------------------------------------------------
    */
    'max_agent_iterations' => env('AI_MAX_ITERATIONS', 15),
];
