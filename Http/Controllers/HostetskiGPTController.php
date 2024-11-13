<?php

namespace Modules\HostetskiGPT\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use App\Thread;
use App\Mailbox;
use Modules\HostetskiGPT\Entities\GPTSettings;
use Modules\HostetskiGPT\Entities\ConversationSummary;

class HostetskiGPTController extends Controller
{

    /**
     * Display a listing of the resource.
     * @return Response
     */
    public function index()
    {
        return view('hostetskigpt::index');
    }

    /**
     * Show the form for creating a new resource.
     * @return Response
     */
    public function create()
    {
        return view('hostetskigpt::create');
    }

    /**
     * Store a newly created resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function store(Request $request)
    {
    }

    /**
     * Show the specified resource.
     * @return Response
     */
    public function show()
    {
        return view('hostetskigpt::show');
    }

    /**
     * Show the form for editing the specified resource.
     * @return Response
     */
    public function edit()
    {
        return view('hostetskigpt::edit');
    }

    /**
     * Update the specified resource in storage.
     * @param  Request $request
     * @return Response
     */
    public function update(Request $request)
    {
    }

    /**
     * Remove the specified resource from storage.
     * @return Response
     */
    public function destroy()
    {
    }

    public function generate(Request $request) {
        if (Auth::user() === null) return Response::json(["error" => "Unauthorized"], 401);
        $settings = GPTSettings::findOrFail($request->get("mailbox_id"));
        $openaiClient = \Tectalic\OpenAi\Manager::build(new \GuzzleHttp\Client(
            [
                'timeout' => config('app.curl_timeout'),
                'connect_timeout' => config('app.curl_connect_timeout'),
                'proxy' => config('app.proxy'),
            ]
        ), new \Tectalic\OpenAi\Authentication($settings->api_key));

        $command = $request->get("command");
        $messages = [[
            'role' => 'system',
            'content' => $command ?? $settings->start_message
        ]];

        if ($settings->client_data_enabled) {
            $customerName = $request->get("customer_name");
            $customerEmail = $request->get("customer_email");
            $conversationSubject = $request->get("conversation_subject");
            array_push($messages, [
                'role' => 'system',
                'content' => __('Conversation subject is ":subject", customer name is ":name", customer email is ":email"', [
                    'subject' => $conversationSubject,
                    'name' => $customerName,
                    'email' => $customerEmail
                ])
            ]);
        }

        array_push($messages, [
            'role' => 'user',
            'content' => $request->get('query')
        ]);

        $response = $openaiClient->chatCompletions()->create(
        new \Tectalic\OpenAi\Models\ChatCompletions\CreateRequest([
            'model'  => $settings->model,
            'messages' => $messages,
            'max_tokens' => (integer) $settings->token_limit
        ])
        )->toModel();

        $thread = Thread::find($request->get('thread_id'));
        if ($thread->chatgpt === null) {
            $answers = [];
        } else {
            $answers = json_decode($thread->chatgpt, true);
        }
        if ($answers === null) {
            $answers = [];
        }
        array_push($answers, trim($response->choices[0]->message->content, "\n"));
        $thread->chatgpt = json_encode($answers, JSON_UNESCAPED_UNICODE);
        $thread->save();

        return Response::json([
            'query' => $request->get('query'),
            'answer' => $response->choices[0]->message->content
        ], 200);
    }

    public function answers(Request $request) {
        if (Auth::user() === null) return Response::json(["error" => "Unauthorized"], 401);
        $conversation = $request->query('conversation');
        $threads = Thread::where("conversation_id", $conversation)->get();
        $result = [];
        foreach ($threads as $thread) {
            if ($thread->chatgpt !== "{}" && $thread->chatgpt !== null) {
                $answers = [];
                $answers_text = json_decode($thread->chatgpt, true);
                if ($answers_text === null) continue;
                foreach ($answers_text as $answer_text) {
                    array_push($answers, $answer_text);
                }
                $answer = ["thread" => $thread->id, "answers" => $answers];
                array_push($result, $answer);
            }
        }
        return Response::json(["answers" => $result], 200);
    }

    public function settings($mailbox_id) {
        $mailbox = Mailbox::findOrFail($mailbox_id);

        $settings = GPTSettings::find($mailbox_id);

        if (empty($settings)) {
            $settings['mailbox_id'] = $mailbox_id;
            $settings['api_key'] = "";
            $settings['token_limit'] = "";
            $settings['start_message'] = "";
            $settings['enabled'] = false;
            $settings['model'] = "";
            $settings['client_data_enabled'] = false;
            $settings['summary_prompt'] = "Erstelle eine kurze Zusammenfassung der folgenden Konversation. Fokussiere dich auf die wichtigsten Punkte und den aktuellen Status.";
        }

        return view('hostetskigpt::settings', [
            'mailbox'   => $mailbox,
            'settings'  => $settings
        ]);
    }

    public function saveSettings($mailbox_id, Request $request) {
        //return $request->get('model');
        GPTSettings::updateOrCreate(
            ['mailbox_id' => $mailbox_id],
            [
                'api_key' => $request->get("api_key"),
                'enabled' => isset($_POST['gpt_enabled']),
                'token_limit' => $request->get('token_limit'),
                'start_message' => $request->get('start_message'),
                'model' => $request->get('model'),
                'client_data_enabled' => isset($_POST['show_client_data_enabled']),
                'summary_prompt' => $request->get('summary_prompt')
            ]
        );

        return redirect()->route('hostetskigpt.settings', ['mailbox_id' => $mailbox_id]);
    }

    public function checkIsEnabled(Request $request) {
        $settings = GPTSettings::find($request->query("mailbox"));
        if (empty($settings)) {
            return Response::json(['enabled'=> false], 200);
        }
        return Response::json(['enabled' => $settings['enabled']], 200);
    }

    public function generateSummary(Request $request) {
        \Log::info("Starting summary generation");
        \Log::info("Request params:", $request->all());
        
        try {
            if (Auth::user() === null) {
                \Log::error("Unauthorized summary request");
                return Response::json(["error" => "Unauthorized"], 401);
            }

            $mailbox_id = $request->get("mailbox_id");
            $conversation_id = $request->get("conversation_id");

            \Log::info("Processing summary for mailbox {$mailbox_id}, conversation {$conversation_id}");

            $settings = GPTSettings::findOrFail($mailbox_id);
            
            $openaiClient = \Tectalic\OpenAi\Manager::build(new \GuzzleHttp\Client(
                [
                    'timeout' => config('app.curl_timeout'),
                    'connect_timeout' => config('app.curl_connect_timeout'),
                    'proxy' => config('app.proxy'),
                ]
            ), new \Tectalic\OpenAi\Authentication($settings->api_key));

            $messages = [[
                'role' => 'system',
                'content' => $settings->summary_prompt
            ]];

            $threads = Thread::where("conversation_id", $conversation_id)
                ->orderBy('created_at', 'asc')
                ->get();

            foreach ($threads as $thread) {
                array_push($messages, [
                    'role' => 'user',
                    'content' => strip_tags($thread->body)
                ]);
            }

            $response = $openaiClient->chatCompletions()->create(
                new \Tectalic\OpenAi\Models\ChatCompletions\CreateRequest([
                    'model'  => $settings->model,
                    'messages' => $messages,
                    'max_tokens' => 250
                ])
            )->toModel();

            return Response::json([
                'summary' => $response->choices[0]->message->content
            ]);
        } catch (\Exception $e) {
            \Log::error("Summary generation failed: " . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return Response::json(["error" => $e->getMessage()], 500);
        }
    }

    public function getSummary(Request $request)
    {
        try {
            \Log::info('Starting getSummary', [
                'request' => $request->all(),
                'user' => auth()->user() ? auth()->user()->id : 'not authenticated'
            ]);
            
            if (!auth()->check()) {
                throw new \Exception('User not authenticated');
            }

            $conversation_id = $request->get('conversation_id');
            $mailbox_id = $request->get('mailbox_id');
            
            \Log::info('Parameters', [
                'conversation_id' => $conversation_id,
                'mailbox_id' => $mailbox_id
            ]);

            if (!$conversation_id || !$mailbox_id) {
                throw new \Exception('Missing required parameters');
            }

            // Prüfe ob eine existierende Zusammenfassung vorhanden ist
            $existingSummary = ConversationSummary::where('conversation_id', $conversation_id)
                ->first();
            
            \Log::info('Existing summary check', [
                'exists' => $existingSummary ? 'yes' : 'no'
            ]);

            if ($request->get('force_refresh', false) || !$existingSummary) {
                // Lsche alte Zusammenfassung wenn vorhanden
                if ($existingSummary) {
                    $existingSummary->delete();
                }
                
                // Prüfe GPT Settings
                try {
                    $settings = GPTSettings::findOrFail($mailbox_id);
                    \Log::info('GPT Settings found', [
                        'has_api_key' => !empty($settings->api_key),
                        'model' => $settings->model,
                        'token_limit' => $settings->token_limit
                    ]);
                } catch (\Exception $e) {
                    \Log::error('GPT Settings error', [
                        'error' => $e->getMessage(),
                        'mailbox_id' => $mailbox_id
                    ]);
                    throw new \Exception('GPT settings not found for mailbox');
                }
                
                if (empty($settings->api_key)) {
                    throw new \Exception('API key not configured');
                }

                // Hole alle Nachrichten der Konversation
                $threads = Thread::where('conversation_id', $conversation_id)
                    ->orderBy('created_at', 'asc')
                    ->get();

                \Log::info('Threads found', [
                    'count' => $threads->count()
                ]);

                if ($threads->isEmpty()) {
                    return response()->json(['summary' => 'Keine Nachrichten gefunden.']);
                }

                $messages = [
                    [
                        'role' => 'system',
                        'content' => $settings->summary_prompt ?? 'Erstelle eine kurze Zusammenfassung der folgenden Konversation. Fokussiere dich auf die wichtigsten Punkte und den aktuellen Status.'
                    ]
                ];

                foreach ($threads as $thread) {
                    if (!empty($thread->body)) {
                        $messages[] = [
                            'role' => 'user',
                            'content' => strip_tags($thread->body)
                        ];
                    }
                }

                \Log::info('Prepared messages for API', [
                    'message_count' => count($messages)
                ]);

                try {
                    $openaiClient = \Tectalic\OpenAi\Manager::build(
                        new \GuzzleHttp\Client([
                            'timeout' => config('app.curl_timeout', 30),
                            'connect_timeout' => config('app.curl_connect_timeout', 10),
                            'proxy' => config('app.proxy')
                        ]), 
                        new \Tectalic\OpenAi\Authentication($settings->api_key)
                    );

                    \Log::info('Making API request', [
                        'model' => $settings->model ?? 'gpt-3.5-turbo',
                        'max_tokens' => min((int) $settings->token_limit, 500)
                    ]);

                    $response = $openaiClient->chatCompletions()->create(
                        new \Tectalic\OpenAi\Models\ChatCompletions\CreateRequest([
                            'model' => $settings->model ?? 'gpt-3.5-turbo',
                            'messages' => $messages,
                            'max_tokens' => min((int) $settings->token_limit, 500)
                        ])
                    )->toModel();

                    $summaryText = $response->choices[0]->message->content;

                    \Log::info('Got API response', [
                        'summary_length' => strlen($summaryText)
                    ]);

                    // Speichere die Zusammenfassung
                    try {
                        ConversationSummary::create([
                            'conversation_id' => $conversation_id,
                            'summary' => $summaryText,
                            'last_updated' => now()
                        ]);
                        \Log::info('Summary saved to database');
                    } catch (\Exception $e) {
                        \Log::error('Database save error', [
                            'error' => $e->getMessage()
                        ]);
                        throw new \Exception('Could not save summary to database: ' . $e->getMessage());
                    }

                    return response()->json(['summary' => nl2br($summaryText)]);

                } catch (\Exception $e) {
                    \Log::error('OpenAI API Error', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    throw new \Exception('Fehler bei der OpenAI API-Anfrage: ' . $e->getMessage());
                }
            } else {
                return response()->json(['summary' => nl2br($existingSummary->summary)]);
            }

        } catch (\Exception $e) {
            \Log::error('Summary generation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'error' => 'Fehler bei der Generierung der Zusammenfassung: ' . $e->getMessage()
            ], 500);
        }
    }

}
