<?php

namespace App\Services;

use App\Http\Helper;
use App\TopicFlag;
use App\TopicPostReaction;
use App\TopicRead;
use App\User;
use ErrorException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Query;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;

class DiscourseService
{
    private array $config;

    private Client $client;

    public function __construct(array $config)
    {
        $this->config = $config;

        $this->client = new Client([
            'base_uri' => $this->config['url'],
            'headers' => [
                'Api-Key' => $this->config['api_key'],
                'Api-Username' => $this->config['admin_username'],
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
        ]);
    }

    public function attestationRate($id)
    {
        try {
            return TopicRead::where('topic_id', $id)->count() / User::where('is_member', true)->count() * 100;
        } catch (ErrorException $e) {
            return 0;
        }
    }

    public function updateTopic($id, array $data, string $username)
    {
        return $this->try(function () use ($id, $data, $username) {
            $response = $this->client->put("/t/-/{$id}.json", $this->by($username, [
                'form_params' => $data,
            ]));

            return $this->json($response);
        });
    }

    public function createPost(array $data, string $username)
    {
        return $this->try(function () use ($data, $username) {
            return $this->json(
                $this->client->post('/posts.json', $this->by($username, [
                    'form_params' => $data,
                ]))
            );
        });
    }

    public function updatePost($id, array $data, string $username)
    {
        return $this->try(function () use ($id, $data, $username) {
            return $this->json(
                $this->client->put("/posts/{$id}.json", $this->by($username, [
                    'form_params' => $data,
                ]))
            );
        });
    }

    public function deletePost(string $id, string $username)
    {
        return $this->try(function () use ($id, $username) {
            $this->client->delete("/posts/{$id}.json", $this->by($username));

            return $this->post($id, $username);
        });
    }

    public function postReplies(string $id, string $username)
    {
        return $this->try(function () use ($id, $username) {
            return $this->json($this->client->get("/posts/{$id}/replies.json", $this->by($username)));
        });
    }

    public function posts(string $username)
    {
        return $this->try(function () use ($username) {
            return $this->json($this->client->get('/posts.json', $this->by($username)));
        });
    }

    public function postsByTopicId($id, string $postIds, string $username)
    {
        return $this->try(function () use ($id, $postIds, $username) {
            $response = $this->client->get("/t/{$id}/posts.json", $this->by($username, [
                'query' => Query::build(['post_ids[]' => explode(',', $postIds)]),
            ]));

            return $this->json($response)['post_stream']['posts'];
        });
    }

    public function like($id, string $username)
    {
        return $this->try(function () use ($id, $username) {
            $response = $this->client->post('/post_actions.json', $this->by($username, [
                'form_params' => [
                    'id' => $id,
                    'post_action_type_id' => 2,
                    'flag_topic' => false,
                ],
            ]));

            return $this->json($response);
        });
    }

    public function unlike($id, string $username)
    {
        return $this->try(function () use ($id, $username) {
            $response = $this->client->delete("/post_actions/{$id}.json", $this->by($username, [
                'form_params' => [
                    'post_action_type_id' => 2,
                ],
            ]));

            return $this->json($response);
        });
    }

    public function post($id, string $username)
    {
        return $this->try(function () use ($id, $username) {
            $response = $this->client->get("/posts/{$id}.json", $this->by($username));

            return $this->json($response);
        });
    }

    public function isLikedTo($id, string $username)
    {
        return $this->try(function () use ($id, $username) {
            $post = $post = $this->post($id, $username);

            $action = head(array_filter($post['actions_summary'] ?? [], fn ($action) => $action['id'] === 2));

            if ($action === false) {
                return false;
            }

            return $action['acted'] ?? false;
        });
    }

    public function topic($id, string $username)
    {
        return $this->try(function () use ($id, $username) {
            $response = $this->client->get("/t/{$id}.json", $this->by($username));

            return $this->json($response);
        });
    }

    public function topics(string $username, int $page = 0)
    {
        return $this->try(function () use ($username, $page) {
            $response = $this->json(
                $this->client->get('/latest.json', $this->by($username, [
                    'query' => ['page' => $page],
                ]))
            );

            $topicIds = array_map(fn ($topic) => $topic['id'], $response['topic_list']['topics']);

            $proposals = DB::table('proposal')
                ->select('id', 'discourse_topic_id', 'status', 'dos_paid')
                ->whereIn('discourse_topic_id', $topicIds)
                ->get();

            $attestationRates = DB::table('topic_reads')
                ->select('topic_id', DB::raw('count(*) as count'))
                ->whereIn('topic_id', $topicIds)
                ->groupBy('topic_id')
                ->get();

            $attestationUsers = DB::table('topic_reads')
                ->select('topic_id', 'user_id')
                ->whereIn('topic_id', $topicIds)
                ->get();

            $VACount = User::where('is_member', true)->count();

            foreach ($response['topic_list']['topics'] as $key => $topic) {
                $proposal = $proposals->firstWhere('discourse_topic_id', $topic['id']);

                if (is_null($proposal)) {
                    continue;
                }

                $count = $attestationRates->firstWhere('topic_id', $topic['id'])->count ?? 0;

                $response['topic_list']['topics'][$key]['proposal'] = [
                    'id' => $proposal->id,
                    'attestation_rate' => $count / $VACount * 100,
                    'status' => Helper::getStatusProposal($proposal),
                    'is_attestated' => $attestationUsers->where('user_id', Auth::id())->where('topic_id', $topic['id'])->isNotEmpty(),
                ];
            }

            return $response['topic_list'];
        });
    }

    public function search($term, string $username)
    {
        return $this->try(function () use ($term, $username) {
            $response = $this->client->get('/search.json', $this->by($username, [
                'query' => [
                    'q' => $term,
                ],
            ]));

            return $this->json($response);
        });
    }

    public function register(User $user)
    {
        return $this->try(function () use ($user) {
            $response = $this->client->post('/users.json', [
                'form_params' => [
                    'name' => $user->name,
                    'username' => $this->getUsername($user),
                    'email' => $user->email,
                    'password' => Str::random(32),
                    'active' => true,
                    'approved' => true,
                ],
            ]);

            return $this->json($response);
        });
    }

    public function grantModeration(int $userId)
    {
        return $this->try(function () use ($userId) {
            $response = $this->client->put("/admin/users/{$userId}/grant_moderation.json");

            return $this->json($response);
        });
    }

    public function grantAdmin(int $userId)
    {
        return $this->try(function () use ($userId) {
            $response = $this->client->put("/admin/users/{$userId}/grant_admin.json");

            return $this->json($response);
        });
    }

    public function user(string $username)
    {
        try {
            $response = $this->client->get("/u/{$username}.json");

            return $this->json($response);
        } catch (ClientException $e) {
            return null;
        }
    }

    public function mergePostsWithDxD(array $posts)
    {
        $postIds = array_map(fn ($post) => $post['id'], $posts);
        $discourseUserIds = array_unique(array_map(fn ($post) => $post['user_id'], $posts));

        $topicFlags = TopicFlag::query()
            ->select('id', 'topic_id', 'post_id', 'reason')
            ->whereIn('post_id', $postIds)
            ->get();

        $users = User::query()
            ->select('users.discourse_user_id', DB::raw('Sum(`reputation`.`value`) as reputation'))
            ->join('reputation', 'reputation.user_id', '=', 'users.id')
            ->whereIn('users.discourse_user_id', $discourseUserIds)
            ->get();

        $reactions = TopicPostReaction::query()
            ->select('post_id', 'user_id', 'type')
            ->whereIn('post_id', $postIds)
            ->get();

        foreach ($posts as $key => $post) {
            $posts[$key]['flag'] = $topicFlags->firstWhere('post_id', $post['id']);
            $posts[$key]['devxdao_user'] = $users->firstWhere('discourse_user_id', $post['user_id']);
            $posts[$key]['reactions'] = resolve(TopicPostReactionService::class)->format($post['id'], $reactions);
        }

        return $posts;
    }

    public function getUsername(User $user)
    {
        return Str::slug($user->profile->forum_name);
    }

    private function json(ResponseInterface $response)
    {
        return json_decode($response->getBody()->getContents(), true);
    }

    private function by(string $username, array $data = [])
    {
        return array_merge($data, [
            'headers' => array_merge($data['headers'] ?? [], [
                'Api-Username' => $username,
            ])
        ]);
    }

    private function try(callable $callable)
    {
        try {
            return $callable();
        } catch (ClientException $e) {
            if (app()->environment('local')) {
                info($e);
            }

            info('Error on Discourse API', [
                'username' => $this->getUsername(Auth::user()),
                'message' => $e->getMessage(),
                'response' => $e->getResponse(),
            ]);

            $errors = $this->json($e->getResponse())['errors'] ?? ['Please try again.'];

            return ['failed' => true, 'message' => head($errors)];
        }
    }
}
