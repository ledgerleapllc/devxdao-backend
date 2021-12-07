<?php

namespace App\Http\Controllers;

use App\FinalGrant;
use App\Http\Helper;
use App\IpHistory;
use App\IpHistoryOps;
use App\Mail\LoginTwoFA;
use App\Mail\UserAlert;
use App\Milestone;
use App\MilestoneCheckList;
use App\MilestoneReview;
use App\OpsUser;
use App\Profile;
use App\Proposal;
use App\User;
use App\Vote;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OpsController extends Controller
{
    public function login(Request $request)
    {
        // Validator
        $validator = Validator::make($request->all(), [
            'email' => 'required',
            'password' => 'required'
        ]);
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Email or Password is not correct'
            ];
        }

        $email = $request->get('email');
        $password = $request->get('password');

        $user = OpsUser::where('email', $email)->first();
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Email does not exist'
            ];
        }

        if ($user->is_super_admin == 1 ||  $user->is_pa == 1) {
            if (!Hash::check($password, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Email or Password is not correct'
                ];
            }

            if ($user->status == 'denied' || $user->banned == 1) {
                return [
                    'success' => false,
                    'message' => 'You are banned. Please contact us for further details.'
                ];
            }

            $user->last_login_ip_address = request()->ip();
            $user->last_login_at = now();
            $user->save();
            $ipHistory = new IpHistoryOps();
            $ipHistory->user_id = $user->id;
            $ipHistory->ip_address = request()->ip();
            $ipHistory->save();

            $tokenResult = $user->createToken('User Access Token');
            $user->accessTokenAPI = $tokenResult->accessToken;

            return [
                'success' => true,
                'user' => $user
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Role is not valid'
            ];
        }
        return [
            'success' => false,
            'message' => 'Login info is not correct'
        ];
    }

    public function logout()
    {
        auth()->user()->token()->revoke();
        return [
            'success' => true,
        ];
    }

    public function getMeOps()
    {
        $user = Auth::user();
        return [
            'success' => true,
            'me' => $user
        ];
    }

    public function createPAUser(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Invalid format Email'
            ];
        }

        $isExist = OpsUser::where(['email' => $request->email])->count() > 0;
        if ($isExist) {
            return [
                'success' => false,
                'message' => 'This email has already been exist'
            ];
        }
        $user = new OpsUser;
        $user->first_name = 'Faker';
        $user->last_name = 'Faker';
        $user->email =  $request->email;
        $user->password = bcrypt($request->password);
        $user->status = 'active';
        $user->is_pa = 1;
        $user->save();
        return [
            'success' => true,
        ];
    }

    public function getListUser(Request $request)
    {
        // Users DataTable
        $user = Auth::user();
        $users = [];

        // Variables
        $search = $request->search;
        $sort_key = $sort_direction = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'ops_users.id';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = $request->limit ?? 15;
        $start = $limit * ($page_id - 1);

        // Records
        if ($user) {
            $users = OpsUser::where('id', '!=', $user->id)
                ->where(function ($query) use ($search) {
                    $query->where('ops_users.is_super_admin', 1)
                        ->orWhere('ops_users.is_pa', 1);
                })->where(function ($query) use ($search) {
                    if ($search) {
                        $query->where('ops_users.email', 'like', '%' . $search . '%');
                    }
                })
                ->orderBy($sort_key, $sort_direction)
                ->offset($start)
                ->limit($limit)
                ->get();
            return [
                'success' => true,
                'users' => $users,
                'finished' => count($users) < $limit ? true : false
            ];
        } else {
            return [
                'success' => false,
                'users' => $users,
            ];
        }
    }

    public function getIpHistories(Request $request, $id)
    {
        $user = OpsUser::find($id);
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Not found user'
            ];
        }
        // Variables
        $sort_key = $sort_direction = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'created_at';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = $request->limit ?? 15;
        $start = $limit * ($page_id - 1);

        // Records
        $ips = IpHistoryOps::where('user_id', $user->id)
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();
        return [
            'success' => true,
            'ip-histories' => $ips,
            'finished' => count($ips) < $limit ? true : false
        ];
    }

    public function revokeUser(Request $request, $id)
    {
        $user = OpsUser::find($id);
        if ($user && $user->is_pa == 1) {
            $user->banned = 1;
            $user->status = 'revoked';
            $user->save();
            return [
                'success' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => 'No user to revoke'
            ];
        }
    }

    public function resetPassword(Request $request, $id)
    {
        $user = OpsUser::find($id);
        if ($user && $user->is_pa == 1) {
            $validator = Validator::make($request->all(), [
                'password' => 'required',
            ]);
            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Invalid password'
                ];
            }
            $user->password = bcrypt($request->password);
            $user->save();
            return ['success' => true];
        } else {
            return [
                'success' => false,
                'message' => 'User not found'
            ];
        }
    }

    public function undoRevokeUser(Request $request, $id)
    {
        $user = OpsUser::find($id);
        if ($user && $user->is_pa == 1) {
            $user->banned = 0;
            $user->status = 'active';
            $user->save();
            return [
                'success' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => 'No admin to un-revoke'
            ];
        }
    }

    public function getListMilestoneJob(Request $request)
    {
        $user = Auth::user();
        $search = $request->search;
        // Variables
        $sort_key = $sort_direction = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'milestone_review.created_at';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $start = $limit * ($page_id - 1);

        $milestones = MilestoneReview::where('milestone_review.status', 'pending')
            ->with(['milestones', 'milestoneSubmitHistory'])
            ->join('milestone', 'milestone.id', '=', 'milestone_review.milestone_id')
            ->join('proposal', 'milestone.proposal_id', '=', 'proposal.id')
            ->join('users', 'proposal.user_id', '=', 'users.id')
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('proposal.id', 'like', '%' . $search . '%')
                        ->orWhere('proposal.title', 'like', '%' . $search . '%')
                        ->orWhere('users.email', 'like', '%' . $search . '%');
                }
            })
            ->select([
                'milestone.*',
                'milestone_review.milestone_id',
                'milestone_review.proposal_id',
                'milestone_review.id as milestone_review_id',
                'milestone_review.id as id',
                'milestone_review.time_submit',
                'proposal.title as proposal_title',
                'users.id as user_id',
                'users.email'
            ])
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();

        return [
            'success' => true,
            'milestones' => $milestones,
            'finished' => count($milestones) < $limit ? true : false
        ];
    }

    public function getListUserPA(Request $request)
    {
        // Users DataTable
        $user = Auth::user();
        $users = [];

        // Variables
        $sort_key = $sort_direction = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'ops_users.id';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = $request->limit ?? 15;
        $start = $limit * ($page_id - 1);

        // Records
        $users = OpsUser::where('banned', 0)
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();
        return [
            'success' => true,
            'users' => $users,
            'finished' => count($users) < $limit ? true : false
        ];
    }

    public function getMilestoneDetail($milestoneReviewId)
    {
        $milestone = MilestoneReview::with([ 'milestoneSubmitHistory' , 'milestoneCheckList',
            'milestones', 'proposal', 'proposal.members',
            'proposal.grants', 'proposal.citations', 'proposal.citations.repProposal', 'proposal.citations.repProposal.user', 'proposal.files'
        ])
            ->join('milestone', 'milestone.id', '=', 'milestone_review.milestone_id')
            ->join('proposal', 'milestone.proposal_id', '=', 'proposal.id')
            ->join('users', 'proposal.user_id', '=', 'users.id')
            ->leftJoin('ops_users as u1', 'u1.id', '=', 'milestone_review.assigner_id')
            ->select([
                'milestone.*',
                'proposal.title as proposal_title',
                'proposal.status as proposal_status',
                'users.id as user_id',
                'users.email',
                'milestone_review.reviewed_at',
                'milestone_review.id as milestone_review_id',
                'milestone_review.id as id',
                'milestone_review.milestone_id',
                'milestone_review.assigner_id',
                'milestone_review.assigned_at',
                'milestone_review.time_submit',
                'milestone_review.status as milestone_review_status',
                'u1.email as assigner_email',
            ])
            ->where('milestone_review.id', $milestoneReviewId)->first();
        if ($milestone) {
            $milestone->support_file_url = $milestone->support_file ? asset($milestone->support_file) : null;
            $previous_check_list = MilestoneCheckList::where('milestone_id', $milestone->milestone_id)->orderBy('created_at', 'desc')->first();
            return [
                'success' => true,
                'milestone' => $milestone,
                'previous_check_list' => $previous_check_list,
            ];
        }

        return [
            'success' => false,
            'message' => 'This milestone does not exist'
        ];
    }

    public function milestoneAssign($milestoneReviewId, Request $request)
    {
        $admin = Auth::user();
        // Validator
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
        ]);
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Please input user_id'
            ];
        }
        $assigner = OpsUser::where('id', $request->user_id)->where('banned', 0)->first();
        if (!$assigner) {
            return [
                'success' => false,
                'message' => 'This user does not exist'
            ];
        }
        $milestone_review = MilestoneReview::where('id', $milestoneReviewId)->first();
        if (!$milestone_review) {
            return [
                'success' => false,
                'message' => 'This milestone does not exist'
            ];
        }
        $milestoneId = $milestone_review->milestone_id;
        $milestone_review->assigner_id = $request->user_id;
        $milestone_review->status = 'active';
        $milestone_review->assigned_at = now();
        $milestone_review->save();
        $milestone = Milestone::find($milestoneId);
        $proposal = Proposal::find($milestone->proposal_id);
        $op = User::find($proposal->user_id);
        if (Helper::checkSendMailTriggerMember('PM Reviewer assigned')) {
            $title = "Reviewer assigned to your DEVxDAO Grant Milestone";
            $body = "$op->first_name, <br><br>A reviewer has been assigned to review the milestone you submitted towards your grant $proposal->title. Please allow up to a week for this process and look our for our next email informing you of your submission review status.<br><br>
            If your milestone is approved as delivered, your proposal will moving automatically to voting. If it needs work, the review team will reply with any needed notes.<br> <br>
            Thank you for being part of the program, <br> <br>
            DxD Program Management";
            Mail::to($op)->send(new UserAlert($title, $body));
        }
        return [
            'success' => true,
        ];
    }

    public function milestoneUnassign($milestoneReviewId, Request $request)
    {
        $admin = Auth::user();
        $milestone_review = MilestoneReview::where('id', $milestoneReviewId)->where('status', 'active')->first();
        if (!$milestone_review) {
            return [
                'success' => false,
                'message' => 'This milestone does not exist'
            ];
        }
        $milestone_review->assigner_id = null;
        $milestone_review->status = 'pending';
        $milestone_review->assigned_at = null;
        $milestone_review->save();
        return [
            'success' => true,
        ];
    }

    public function getListMilestoneAssigned(Request $request)
    {
        $user = Auth::user();

        // Variables
        $sort_key = $sort_direction = '';
        $search = $request->search;
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'milestone_review.assigned_at';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $start = $limit * ($page_id - 1);
        $status = $request->status == 'completed' ? 'approved' : 'active';

        $milestones = MilestoneReview::where('milestone_review.status', '!=', 'pending')
            ->with(['milestones', 'milestoneSubmitHistory'])
            ->join('milestone', 'milestone.id', '=', 'milestone_review.milestone_id')
            ->join('proposal', 'milestone.proposal_id', '=', 'proposal.id')
            ->join('ops_users', 'milestone_review.assigner_id', '=', 'ops_users.id')
            ->where(function ($query) use ($search, $status) {
                if ($search) {
                    $query->where('proposal.id', 'like', '%' . $search . '%')
                        ->orWhere('proposal.title', 'like', '%' . $search . '%')
                        ->orWhere('ops_users.email', 'like', '%' . $search . '%');
                }
                if ($status == 'active') {
                    $query->where('milestone_review.status', 'active');
                } else {
                    $query->whereIn('milestone_review.status', ['approved', 'denied']);
                }
            })
            ->select([
                'milestone.*',
                'milestone_review.milestone_id',
                'milestone_review.assigner_id',
                'milestone_review.assigned_at',
                'milestone_review.reviewed_at',
                'milestone_review.status as milestone_review_status',
                'milestone_review.id as milestone_review_id',
                'milestone_review.id as id',
                'milestone_review.time_submit',
                'proposal.title as proposal_title',
                'ops_users.email'
            ])
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();
        return [
            'success' => true,
            'milestones' => $milestones,
            'finished' => count($milestones) < $limit ? true : false
        ];
    }


    public function myAssignJobMilestone(Request $request)
    {
        $user = Auth::user();

        // Variables
        $sort_key = $sort_direction = '';
        $search = $request->search;
        $show_all = $request->show_all;
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'milestone_review.assigned_at';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $start = $limit * ($page_id - 1);
        $status = $request->status == 'completed' ? 'approved' : 'active';

        $milestones = MilestoneReview::where('milestone_review.status', '!=', 'pending')
            ->with(['milestones', 'milestoneSubmitHistory'])
            ->join('milestone', 'milestone.id', '=', 'milestone_review.milestone_id')
            ->join('proposal', 'milestone.proposal_id', '=', 'proposal.id')
            ->join('ops_users', 'milestone_review.assigner_id', '=', 'ops_users.id')
            ->where(function ($query) use ($search, $show_all, $user, $status) {
                if ($search) {
                    $query->where('proposal.id', 'like', '%' . $search . '%')
                        ->orWhere('proposal.title', 'like', '%' . $search . '%')
                        ->orWhere('ops_users.email', 'like', '%' . $search . '%');
                }
                if ($show_all != 1) {
                    $query->where('milestone_review.assigner_id', $user->id);
                }
                if ($status == 'active') {
                    $query->where('milestone_review.status', 'active');
                } else {
                    $query->whereIn('milestone_review.status', ['approved', 'denied']);
                }
            })

            ->select([
                'milestone.*',
                'milestone_review.milestone_id',
                'milestone_review.assigner_id',
                'milestone_review.assigned_at',
                'milestone_review.status as milestone_review_status',
                'milestone_review.id as milestone_review_id',
                'milestone_review.id as id',
                'milestone_review.time_submit',
                'proposal.title as proposal_title',
                'ops_users.email'
            ])
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();
        return [
            'success' => true,
            'milestones' => $milestones,
            'finished' => count($milestones) < $limit ? true : false
        ];
    }

    public function getMilestoneDetailAssign($milestoneReviewId)
    {
        $user = Auth::user();
        $milestone = MilestoneReview::with([ 'milestoneSubmitHistory' , 'milestoneCheckList',
            'milestones', 'proposal', 'proposal.members',
            'proposal.grants', 'proposal.citations', 'proposal.citations.repProposal', 'proposal.citations.repProposal.user', 'proposal.files'
        ])
            ->join('milestone', 'milestone.id', '=', 'milestone_review.milestone_id')
            ->join('proposal', 'milestone.proposal_id', '=', 'proposal.id')
            ->join('users', 'proposal.user_id', '=', 'users.id')
            ->join('ops_users as u1', 'u1.id', '=', 'milestone_review.assigner_id')
            ->select([
                'milestone.*',
                'proposal.title as proposal_title',
                'proposal.status as proposal_status',
                'users.id as user_id',
                'users.email',
                'milestone_review.reviewed_at',
                'milestone_review.assigner_id',
                'milestone_review.assigned_at',
                'milestone_review.id as id',
                'milestone_review.milestone_id',
                'milestone_review.status as milestone_review_status',
                'milestone_review.id as milestone_review_id',
                'milestone_review.time_submit',
                'u1.email as assigner_email',
            ])->where('milestone_review.id', $milestoneReviewId)
            ->where('milestone_review.assigner_id', $user->id)
            ->first();
        if ($milestone) {
            $previous_check_list = MilestoneCheckList::where('milestone_id', $milestone->milestone_id)->orderBy('created_at', 'desc')->first();
            return [
                'success' => true,
                'milestone' => $milestone,
                'previous_check_list' => $previous_check_list,
            ];
        }
        return [
            'success' => false,
            'message' => 'This milestone does not exist'
        ];
    }

    public function submitReviewMilestone($milestoneReviewId, Request $request)
    {
        $user = Auth::user();
        // Validator
        $validator = Validator::make($request->all(), [
            'appl_accepted_definition' => 'nullable|in:0,1',
            'appl_accepted_pm' => 'nullable|in:0,1',
            'appl_attests_accounting' => 'nullable|in:0,1',
            'appl_attests_criteria' => 'nullable|in:0,1',
            'appl_submitted_corprus' => 'nullable|in:0,1',
            'appl_accepted_corprus' => 'nullable|in:0,1',
            'crdao_acknowledged_project' => 'nullable|in:0,1',
            'crdao_accepted_pm' => 'nullable|in:0,1',
            'crdao_acknowledged_receipt' => 'nullable|in:0,1',
            'crdao_submitted_review' => 'nullable|in:0,1',
            'crdao_submitted_subs' => 'nullable|in:0,1',
            'pm_submitted_evidence' => 'nullable|in:0,1',
            'pm_submitted_admin' => 'nullable|in:0,1',
            'pm_verified_corprus' => 'nullable|in:0,1',
            'pm_verified_crdao' => 'nullable|in:0,1',
            'pm_verified_subs' => 'nullable|in:0,1',
        ]);

        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Provide all the necessary information',
                'fail' => $validator->errors(),
            ];
        }
        $data = $validator->validated();
        $collection = collect($data);
        $milestone_review = MilestoneReview::where('id', $milestoneReviewId)->first();
        if ($milestone_review && ($milestone_review->status == 'active')) {
            if ($user->is_super_admin != 1 && $milestone_review->assigner_id != $user->id) {
                return [
                    'success' => false,
                    'message' => 'Cannot submit review milsetone',
                ];
            }
            $milestoneId = $milestone_review->milestone_id;
            $milestone = Milestone::find($milestoneId);
            $proposal = Proposal::find($milestone->proposal_id);
            $op = User::find($proposal->user_id);
            $milestonePosition = Helper::getPositionMilestone($milestone);
            $status = 'active';
            $filteredCheck = $collection->filter(function ($value, $key) {
                return $value == 1;
            })->count();
            $filteredFail = $collection->filter(function ($value, $key) {
                return isset($value) && $value == 0;
            })->count();
            if ($collection->count() ==  $filteredCheck) {
                $status = 'approved';
                if (Helper::checkSendMailTriggerMember('Milestone code review Passed')) {
                    $title = "Your DEVxDAO Grant Milestone is Approved";
                    $body = "$op->first_name,<br> <br>Nice work! Your milestone submission for <b> $proposal->title </b> is Approved. This will now  move forward to voting. Please allow up to 2 weeks for the voting and look our for a next email regarding the votes. <br> <br>Thank you for being part of the program, <br> <br>  DxD Program Management";
                    Mail::to($op)->send(new UserAlert($title, $body));
                }
                Helper::createGrantTracking($proposal->id, "Milestone $milestonePosition approved by CRDAO", 'milestone_'. $milestonePosition .'_approved_crdao');
                Helper::createGrantTracking($proposal->id, "Milestone $milestonePosition approved by Proj. Mngmt.", 'milestone_' . $milestonePosition .'_approved_proj');
            }
            if ($filteredFail > 0) {
                $note_fail = Helper::getNotesFailSubmitReview($request->all());
                $status = 'denied';
                if (Helper::checkSendMailTriggerMember('Milestone code review Failed')) {
                    $title = "Your DEVxDAO Grant Milestone needs some work";
                    $body = "$op->first_name,<br> <br>Unfortunately your milestone submission needs a few adjustments before it can be considered for voting and payment.
                    <br> <br> Please see the notes below and submit this milestone again in the DEVxDAO portal when these items are remedied. <br> <b> $note_fail </b> <br>
                    Thank you for being part of the program,  <br> <br>  DxD Program Management";
                    Mail::to($op)->send(new UserAlert($title, $body));
                }
            }
            $milestone_review->status = $status;
            $milestone_review->reviewer = $user->id;
            $milestone_review->assigner_id = $user->id;
            $milestone_review->reviewed_at = now();
            $milestone_review->save();
            MilestoneCheckList::updateOrCreate(
                ['milestone_review_id' => $milestoneReviewId],
                [
                    'milestone_id' => $milestoneId,
                    'user_id' => $user->id,
                    'appl_accepted_definition' => $request->appl_accepted_definition,
                    'appl_accepted_pm' => $request->appl_accepted_pm,
                    'appl_attests_accounting' => $request->appl_attests_accounting,
                    'appl_attests_criteria' => $request->appl_attests_criteria,
                    'appl_submitted_corprus' => $request->appl_submitted_corprus,
                    'appl_accepted_corprus' => $request->appl_accepted_corprus,
                    'crdao_acknowledged_project' => $request->crdao_acknowledged_project,
                    'crdao_accepted_pm' => $request->crdao_accepted_pm,
                    'crdao_acknowledged_receipt' => $request->crdao_acknowledged_receipt,
                    'crdao_submitted_review' => $request->crdao_submitted_review,
                    'crdao_submitted_subs' => $request->crdao_submitted_subs,
                    'pm_submitted_evidence' => $request->pm_submitted_evidence,
                    'pm_submitted_admin' => $request->pm_submitted_admin,
                    'pm_verified_corprus' => $request->pm_verified_corprus,
                    'pm_verified_crdao' => $request->pm_verified_crdao,
                    'pm_verified_subs' => $request->pm_verified_subs,
                    'crdao_acknowledged_project_notes' => $request->crdao_acknowledged_project_notes,
                    'crdao_accepted_pm_notes' => $request->crdao_accepted_pm_notes,
                    'crdao_acknowledged_receipt_notes' => $request->crdao_acknowledged_receipt_notes,
                    'crdao_submitted_review_notes' => $request->crdao_submitted_review_notes,
                    'crdao_submitted_subs_notes' => $request->crdao_submitted_subs_notes,
                    'pm_submitted_evidence_notes' => $request->pm_submitted_evidence_notes,
                    'pm_submitted_admin_notes' => $request->pm_submitted_admin_notes,
                    'pm_verified_corprus_notes' => $request->pm_verified_corprus_notes,
                    'pm_verified_crdao_notes' => $request->pm_verified_crdao_notes,
                    'pm_verified_subs_notes' => $request->pm_verified_subs_notes,
                    'addition_note' => $request->addition_note,
                ]
            );
            if ($status == 'approved') {
                $proposalId = $milestone_review->proposal_id;
                $finalGrant = FinalGrant::where('proposal_id', $proposalId)->first();
                $milestone = Milestone::find($milestoneId);
                Helper::createMilestoneLog($milestoneId, $user->email, $user->id, 'Admin', 'Admin approved the work.');
                $vote = Vote::where('proposal_id', $proposalId)
                    ->where('type', 'informal')
                    ->where('content_type', 'milestone')
                    ->where('milestone_id', $milestoneId)
                    ->first();

                if (!$vote) {
                    Helper::createMilestoneLog($milestoneId, null, null, 'System', 'Vote started');
                    Helper::createGrantTracking($proposal->id, "Milestone $milestonePosition started informal vote", 'milestone_' . $milestonePosition .'_started_informal_vote');
                    // Submit
                    $vote = new Vote;
                    $vote->proposal_id = $proposalId;
                    $vote->type = "informal";
                    $vote->status = "active";
                    $vote->content_type = "milestone";
                    $vote->milestone_id = $milestoneId;
                    $vote->save();

                    $finalGrant->milestones_submitted = (int) $finalGrant->milestones_submitted + 1;
                    $finalGrant->save();
                    $emailerData = Helper::getEmailerData();
                    Helper::triggerUserEmail($user, 'Milestone Submitted', $emailerData);

                    return [
                        'success' => true,
                        'milestone_review' => $milestone_review
                    ];
                } else {
                    // Re-Submit
                    $finalVote = Vote::where('proposal_id', $proposalId)
                        ->where('type', 'formal')
                        ->where('content_type', 'milestone')
                        ->where('milestone_id', $milestoneId)
                        ->orderBy('id', 'desc')
                        ->first();

                    if ($finalVote && $finalVote->result == "fail") {
                        Helper::createMilestoneLog($milestoneId, null, null, 'System', 'Vote re-started');
                        // Submit
                        $vote = new Vote();
                        $vote->proposal_id = $proposalId;
                        $vote->type = "informal";
                        $vote->status = "active";
                        $vote->content_type = "milestone";
                        $vote->milestone_id = $milestoneId;
                        $vote->save();

                        return [
                            'success' => true,
                            'milestone_review' => $milestone_review
                        ];
                    }
                    return [
                        'success' => true,
                        'milestone_review' => $milestone_review
                    ];
                }
            } else {
                return [
                    'success' => true,
                    'milestone_review' => $milestone_review
                ];
            }
        } else {
            return [
                'success' => false,
                'message' => 'Milestone not found',
            ];
        }
    }

    public function getUsers(Request $request)
    {
        // Users DataTable
        $user = Auth::user();
        $users = [];

        // Variables
        $search = $request->search;
        $sort_key = $sort_direction = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'ops_users.id';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = $request->limit ?? 15;
        $start = $limit * ($page_id - 1);

        // Records
        $users = OpsUser::where('id', '!=', $user->id)
            ->where('ops_users.is_pa', 1)->where('banned', 0)
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('ops_users.email', 'like', '%' . $search . '%');
                }
            })
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();
        return [
            'success' => true,
            'users' => $users,
            'finished' => count($users) < $limit ? true : false
        ];
    }

    public function updateNodeMilestoneReview($milestoneReviewId, Request $request)
    {
        $user = Auth::user();
        $milestone_checklist = MilestoneCheckList::where('milestone_review_id', $milestoneReviewId)->first();
        if (!$milestone_checklist) {
            $milestone_checklist = new MilestoneCheckList();
        }
        $milestoneReview = MilestoneReview::find($milestoneReviewId);
        $milestone_checklist->user_id = $user->id;
        $milestone_checklist->milestone_id = $milestoneReview->milestone_id;
        $milestone_checklist->milestone_review_id = $milestoneReviewId;
        if ($request->crdao_acknowledged_project_notes)
            $milestone_checklist->crdao_acknowledged_project_notes = $request->crdao_acknowledged_project_notes;
        if ($request->crdao_accepted_pm_notes)
            $milestone_checklist->crdao_accepted_pm_notes = $request->crdao_accepted_pm_notes;
        if ($request->crdao_acknowledged_receipt_notes)
            $milestone_checklist->crdao_acknowledged_receipt_notes = $request->crdao_acknowledged_receipt_notes;
        if ($request->crdao_submitted_review_notes)
            $milestone_checklist->crdao_submitted_review_notes = $request->crdao_submitted_review_notes;
        if ($request->crdao_submitted_subs_notes)
            $milestone_checklist->crdao_submitted_subs_notes = $request->crdao_submitted_subs_notes;
        if ($request->pm_submitted_evidence_notes)
            $milestone_checklist->pm_submitted_evidence_notes = $request->pm_submitted_evidence_notes;
        if ($request->pm_submitted_admin_notes)
            $milestone_checklist->pm_submitted_admin_notes = $request->pm_submitted_admin_notes;
        if ($request->pm_verified_corprus_notes)
            $milestone_checklist->pm_verified_corprus_notes = $request->pm_verified_corprus_notes;
        if ($request->pm_verified_crdao_notes)
            $milestone_checklist->pm_verified_crdao_notes = $request->pm_verified_crdao_notes;
        if ($request->pm_verified_subs_notes)
            $milestone_checklist->pm_verified_subs_notes = $request->pm_verified_subs_notes;
        if ($request->addition_note)
            $milestone_checklist->addition_note = $request->addition_note;
        $milestone_checklist->save();
        return [
            'success' => true,
        ];
    }

    // Change Password
    public function changePassword(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            // Validator
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
                'new_password' => 'required'
            ]);
            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Provide all the necessary information'
                ];
            }

            $current_password = $request->get('current_password');
            $new_password = $request->get('new_password');

            if ($current_password == $new_password) {
                return [
                    'success' => false,
                    'message' => 'New password cannot be same as the current one'
                ];
            }

            if (!Hash::check($current_password, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Current password is wrong'
                ];
            }

            $password = Hash::make($new_password);
            $user->password = $password;
            $user->save();

            return ['success' => true];
        }

        return ['success' => false];
    }

    public function checkCurrentPassword(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            // Validator
            $validator = Validator::make($request->all(), [
                'current_password' => 'required',
            ]);
            if ($validator->fails()) {
                return [
                    'success' => false,
                    'message' => 'Provide all the necessary information'
                ];
            }
            $current_password = $request->get('current_password');
            if (!Hash::check($current_password, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Current password is wrong'
                ];
            }
            return ['success' => true];
        }
        return ['success' => false];
    }
}
