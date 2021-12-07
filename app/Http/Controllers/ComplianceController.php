<?php

namespace App\Http\Controllers;

use App\ComplianceUser;
use App\Exports\InvoiceExport;
use App\FinalGrant;
use App\IpHistoryCompliance;
use App\OnBoarding;
use App\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

use App\Http\Helper;
use App\Invoice;
use App\PaymentAddress;
use App\PaymentAddressChange;
use App\Shuftipro;
use App\User;
use App\Vote;
use App\VoteResult;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class ComplianceController extends Controller
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

        $user = ComplianceUser::where('email', $email)->first();
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

            if ($user->status == 'revoked' || $user->banned == 1) {
                return [
                    'success' => false,
                    'message' => 'You are banned. Please contact us for further details.'
                ];
            }

            $user->last_login_ip_address = request()->ip();
            $user->last_login_at = now();
            $user->save();
            $ipHistory = new IpHistoryCompliance();
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

    public function getMe()
    {
        $user = Auth::user();
        // Total Members
        $user->totalMembers = Helper::getTotalMembers();
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

        $isExist = ComplianceUser::where(['email' => $request->email])->count() > 0;
        if ($isExist) {
            return [
                'success' => false,
                'message' => 'This email has already been exist'
            ];
        }
        $user = new ComplianceUser;
        $user->first_name = '';
        $user->last_name = '';
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

        if (!$sort_key) $sort_key = 'compliance_users.id';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = $request->limit ?? 15;
        $start = $limit * ($page_id - 1);

        // Records
        if ($user) {
            $users = ComplianceUser::where('id', '!=', $user->id)
                ->where(function ($query) {
                    $query->where('compliance_users.is_super_admin', 1)
                        ->orWhere('compliance_users.is_pa', 1);
                })->where(function ($query) use ($search) {
                    if ($search) {
                        $query->where('compliance_users.email', 'like', '%' . $search . '%');
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
        $user = ComplianceUser::find($id);
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
        $ips = IpHistoryCompliance::where('user_id', $user->id)
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
        $user = ComplianceUser::find($id);
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
        $user = ComplianceUser::find($id);
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
        $user = ComplianceUser::find($id);
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

    public function updateComplianceStatus(Request $request, $id)
    {
        // Validator
        $validator = Validator::make($request->all(), [
            'compliance' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'compliance must 0 or 1'
            ];
        }
        $user = ComplianceUser::find($id);
        if ($user) {
            $user->compliance = $request->compliance;
            $user->save();
            return [
                'success' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Not found user'
            ];
        }
    }

    public function updatePaidStatus(Request $request, $id)
    {
        // Validator
        $validator = Validator::make($request->all(), [
            'paid' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'paid must 0 or 1'
            ];
        }
        $user = ComplianceUser::find($id);
        if ($user) {
            $user->paid = $request->paid;
            $user->save();
            return [
                'success' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Not found user'
            ];
        }
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
        if ($user) {
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

    // Get Pending Grant Onboardings
    public function getPendingGrantOnboardings(Request $request)
    {
        $user = Auth::user();
        $onboardings = [];

        // Variables
        $sort_key = $sort_direction = $search = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'onboarding.id';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $start = $limit * ($page_id - 1);

        // Records
        $onboardings = OnBoarding::with([
            'user',
            'user.profile',
            'user.shuftipro',
            'user.shuftiproTemp',
            'proposal',
            'proposal.signtureGrants',
            'proposal.grantLogs',
            'proposal.votes',
            'proposal.votes.results',
            'proposal.votes.results.user',
        ])
            ->has('user')
            ->has('proposal')
            ->join('proposal', 'proposal.id', '=', 'onboarding.proposal_id')
            ->join('users', 'users.id', '=', 'onboarding.user_id')
            ->leftJoin('shuftipro', 'shuftipro.user_id', '=', 'onboarding.user_id')
            ->leftJoin('final_grant', 'onboarding.proposal_id', '=', 'final_grant.proposal_id')
            ->whereIn('onboarding.status', ['pending', 'completed'])
            ->where('onboarding.compliance_status', '!=', 'denied')
            ->where('shuftipro.status', '!=', 'denied')
            ->where(function ($query) {
                $query->where('final_grant.status', null)
                    ->orWhere('final_grant.status', 'pending');
            })
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('proposal.title', 'like', '%' . $search . '%');
                }
            })
            ->select([
                'onboarding.*',
                'proposal.title as proposal_title',
                'proposal.include_membership',
                'proposal.short_description',
                'proposal.form_submitted',
                'proposal.signature_request_id',
                'proposal.hellosign_form',
                'proposal.signed_count',
                'shuftipro.status as shuftipro_status',
                'shuftipro.reviewed as shuftipro_reviewed'
            ])
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();

        if ($onboardings) {
            foreach ($onboardings as $onboarding) {
                $user = $onboarding->user;
                if (
                    $user &&
                    isset($user->shuftipro) &&
                    isset($user->shuftipro->data)
                ) {
                    $user->shuftipro_data = json_decode($user->shuftipro->data);
                }
            }
        }
        $onboardings->each(function ($onboarding, $key) {
            $onboarding->user->makeVisible([
                "shuftipro",
                "shuftiproTemp",
            ]);
        });

        return [
            'success' => true,
            'onboardings' => $onboardings,
            'finished' => count($onboardings) < $limit ? true : false
        ];
    }

    // Get Grants
    public function getGrants(Request $request)
    {
        $user = Auth::user();
        // Variables
        $sort_key = $sort_direction = $search = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'final_grant.id';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $start = $limit * ($page_id - 1);
        $status = $request->status ?? 'active';

        $proposals = FinalGrant::with([
            'proposal', 'proposal.user', 'proposal.milestones',
            'proposal.onboarding',
            'proposal.votes',
            'proposal.votes.results',
            'proposal.votes.results.user',
            'proposal.milestones.votes', 'proposal.milestones.votes.results.user', 'grantLogs',
            'user', 'user.shuftipro', 'signtureGrants'
        ])
            ->where('final_grant.status', $status)
            ->has('proposal.milestones')
            ->has('user')
            ->where(function ($subQuery)  use ($search) {
                $subQuery->whereHas('proposal', function ($query) use ($search) {
                    $query->where('proposal.title', 'like', '%' . $search . '%')
                        ->orWhere('proposal.id', 'like', '%' . $search . '%');
                });
            })
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();
        $proposals->each(function ($proposal, $key) {
            $proposal->user->makeVisible([
                "shuftipro",
            ]);
        });
        return [
            'success' => true,
            'proposals' => $proposals,
            'finished' => count($proposals) < $limit ? true : false
        ];
    }

    public function approveComplianceReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'proposalId' => 'required',
        ]);
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Provide all the necessary information'
            ];
        }
        $settings = Helper::getSettings();
        $proposalId = $request->proposalId;
        $proposal = Proposal::find($proposalId);
        $onboarding  = OnBoarding::where('proposal_id', $proposalId)->first();
        if (!$onboarding || !$proposal) {
            return [
                'success' => false,
                'message' => 'Proposal does not exist'
            ];
        }
        if (in_array($onboarding->compliance_status, ['denied', 'approved'])) {
            return [
                'success' => false,
                'message' => "Can not perform this action. Proposal has been $onboarding->compliance_status",
                'data' => [
                    'proposal' => $proposal,
                    'onboarding' => $onboarding,
                    'compliance_admin' => $settings['compliance_admin'],
                ]
            ];
        }
        $onboarding->compliance_status = 'approved';
        $onboarding->compliance_reviewed_at = now();
        $onboarding->save();
        Helper::createGrantTracking($proposalId, "ETA compliance complete", 'eta_compliance_complete');
        $shuftipro = Shuftipro::where('user_id', $onboarding->user_id)->where('status', 'approved')->first();
        if ($shuftipro) {
            $onboarding->status = 'completed';
            $onboarding->save();
            $vote = Vote::find($onboarding->vote_id);
            $op = User::find($onboarding->user_id);
            $emailerData = Helper::getEmailerData();
            if ($vote && $op && $proposal) {
                Helper::triggerUserEmail($op, 'Passed Informal Grant Vote', $emailerData, $proposal, $vote);
            }
            Helper::startFormalVote($vote);
        }
        return [
            'success' => true,
            'proposal' => $proposal,
            'onboarding' => $onboarding,
            'compliance_admin' => $settings['compliance_admin'],
        ];
    }

    public function denyComplianceReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'proposalId' => 'required',
            'reason' => 'required',
        ]);
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Provide all the necessary information'
            ];
        }
        $settings = Helper::getSettings();
        $proposalId = $request->proposalId;
        $proposal = Proposal::find($proposalId);
        $onboarding  = OnBoarding::where('proposal_id', $proposalId)->first();
        if (!$onboarding || !$proposal) {
            return [
                'success' => false,
                'message' => 'Proposal does not exist'
            ];
        }
        if (in_array($onboarding->compliance_status, ['denied', 'approved'])) {
            return [
                'success' => false,
                'message' => "Can not perform this action. Proposal has been $onboarding->compliance_status",
                'data' => [
                    'proposal' => $proposal,
                    'onboarding' => $onboarding,
                    'compliance_admin' => $settings['compliance_admin'],
                ]
            ];
        }
        $onboarding->compliance_status = 'denied';
        $onboarding->compliance_reviewed_at = now();
        $onboarding->deny_reason = $request->reason;
        $onboarding->save();
        return [
            'success' => true,
            'proposal' => $proposal,
            'onboarding' => $onboarding,
            'compliance_admin' => $settings['compliance_admin'],
        ];
    }

    public function getComplianceReview($proposalId)
    {
        $proposal = Proposal::find($proposalId);
        $onboarding  = OnBoarding::where('proposal_id', $proposalId)->first();
        if (!$onboarding || !$proposal) {
            return [
                'success' => false,
                'message' => 'Proposal does not exist'
            ];
        }
        $settings = Helper::getSettings();
        return [
            'success' => true,
            'proposal' => $proposal,
            'onboarding' => $onboarding,
            'compliance_admin' => $settings['compliance_admin'],
        ];
    }

    // Get compliance proposal
    public function getComplianceProposal(Request $request)
    {
        $user = Auth::user();
        $onboardings = [];

        // Variables
        $sort_key = $sort_direction = $search = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'onboarding.id';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $start = $limit * ($page_id - 1);
        $status = $request->status;
        // Records
        $onboardings = OnBoarding::with([
            'user',
            'user.profile',
            'user.shuftipro',
            'user.shuftiproTemp',
            'proposal',
            'proposal.signtureGrants',
            'proposal.grantLogs',
            'proposal.votes',
            'proposal.votes.results',
            'proposal.votes.results.user',
        ])
            ->has('user')
            ->has('proposal')
            ->join('proposal', 'proposal.id', '=', 'onboarding.proposal_id')
            ->join('users', 'users.id', '=', 'onboarding.user_id')
            ->leftJoin('shuftipro', 'shuftipro.user_id', '=', 'onboarding.user_id')
            ->where(function ($query) use ($search) {
                if ($search) {
                    $query->where('proposal.title', 'like', '%' . $search . '%');
                }
            });
        if ($status == 'need-review') {
            $onboardings->where('onboarding.status', 'pending')
                ->where(function ($query) {
                    $query->where('onboarding.compliance_status', 'pending')
                        ->orWhere('shuftipro.status', 'pending')
                        ->orWhere('shuftipro.status', null);
                });
        }

        if ($status == 'approved') {
            $onboardings->where('onboarding.status', 'completed')
                ->where('onboarding.compliance_status', 'approved')
                ->where('shuftipro.status', 'approved');
        }

        if ($status == 'denied') {
            $onboardings->where(function ($query) {
                $query->where('onboarding.compliance_status', 'denied')
                    ->orWhere('shuftipro.status', 'denied');
            });
        }
        $onboardings = $onboardings->select([
            'onboarding.*',
            'proposal.title as proposal_title',
            'proposal.short_description',
            'proposal.form_submitted',
            'proposal.signature_request_id',
            'proposal.hellosign_form',
            'proposal.signed_count',
            'shuftipro.status as shuftipro_status',
            'shuftipro.reviewed as shuftipro_reviewed'
        ])
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();

        $onboardings->each(function ($onboarding, $key) {
            $onboarding->user->makeVisible([
                "profile",
                "shuftipro",
                "shuftiproTemp",
            ]);
        });

        return [
            'success' => true,
            'onboardings' => $onboardings,
            'finished' => count($onboardings) < $limit ? true : false
        ];
    }

    public function getAllInvoices(Request $request)
    {
        $admin = Auth::user();
        $invoices = [];

        // Variables
        $start_date = $request->startDate;
        $end_date = $request->endDate;
        $search = $request->search;
        $show = $request->show;
        $sort_key = $sort_direction = '';
        $page_id = 0;

        $sort_key = $sort_direction = '';
        $search = $start_date = $end_date = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'invoice.id';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $start = $limit * ($page_id - 1);

        $query = Helper::queryGetInvoice($start_date, $end_date, $search);
        $totalGrant = $query->sum('milestone.grant');
        $invoiceCount = $query->count();

        $query->where('invoice.paid', '=', 1);
        $totalPaid = $query->sum('milestone.grant');
        $InvoicePaidCount = $query->count();

        $queryInvoices = Helper::queryGetInvoice($start_date, $end_date, $search);
        if ($show == 'paid') {
            $queryInvoices->where('invoice.paid', 1);
        }
        if ($show == 'unpaid') {
            $queryInvoices->where('invoice.paid', 0);
        }
        $invoices = $queryInvoices->select(['invoice.*'])
            ->with(['proposal', 'proposal.milestones', 'milestone'])
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();

        return [
            'success' => true,
            'totalGrant' => $totalGrant,
            'totalPaid' => $totalPaid,
            'totalUnpaid' => $totalGrant - $totalPaid,
            'invoiceCount' => $invoiceCount,
            'invoicePaidCount' => $InvoicePaidCount,
            'invoiceUnpaidCount' => $invoiceCount - $InvoicePaidCount,
            'finished' => count($invoices) < $limit ? true : false,
            'invoices' => $invoices,
        ];
    }

    public function updateInvoicePaid($id, Request $request)
    {
        $admin = Auth::user();
        $invoice = Invoice::where('id', $id)->first();

        if (!$invoice) {
            return [
                'success' => false,
                'message' => 'Invalid Invoice'
            ];
        }

        if ($admin && ($admin->paid ?? 0)) {
            $invoice->paid = $request->paid;
            $invoice->marked_paid_at = $request->paid ? now() : null;
            $invoice->save();

            return [
                'success' => true,
                'invoice' => $invoice,
            ];
        }

        return [
            'success' => false,
        ];
    }

    public function exportCSVInvoices(Request $request)
    {
        $admin = Auth::user();
        // Variables
        $start_date = $request->startDate;
        $end_date = $request->endDate;
        $search = $request->search;
        $show = $request->show;

        $sort_key = $sort_direction = '';
        $search = $start_date = $end_date = '';
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'invoice.id';
        if (!$sort_direction) $sort_direction = 'desc';

        $queryInvoices = Helper::queryGetInvoice($start_date, $end_date, $search);
        if ($show == 'paid') {
            $queryInvoices->where('invoice.paid', 1);
        }
        if ($show == 'unpaid') {
            $queryInvoices->where('invoice.paid', 0);
        }
        $invoices = $queryInvoices->select(['invoice.*'])
            ->with(['proposal', 'proposal.milestones', 'milestone'])
            ->orderBy($sort_key, $sort_direction)
            ->get();

        return Excel::download(new InvoiceExport($invoices), "invoices_.csv");
    }

    public function getInvoicePdfUrl($invoiceId)
    {
        $invoice = Invoice::with(['proposal', 'milestone', 'user', 'user.profile', 'user.shuftipro'])
            ->where('invoice.id', $invoiceId)
            ->first();

        if (!$invoice) {
            return [
                'success' => false,
                'message' => 'Not found invoice'
            ];
        }
        $vote = Vote::where('milestone_id', $invoice->milestone_id)->where('type', 'formal')
            ->where('result', 'success')->first();
        if (!$vote) {
            return [
                'success' => false,
                'message' => 'Not found vote formal milestone'
            ];
        }
        $milestoneResult = Helper::getResultMilestone($invoice->milestone);
        $invoice->milestone_number =  $milestoneResult['Milestone'];
        $invoice->public_grant_url = config('app.fe_url') . "/public-proposals/$invoice->proposal_id";

        $vote->results = VoteResult::join('profile', 'profile.user_id', '=', 'vote_result.user_id')
            ->where('vote_id', $vote->id)
            ->where('proposal_id', $invoice->proposal_id)
            ->select([
                'vote_result.*',
                'profile.forum_name'
            ])
            ->orderBy('vote_result.created_at', 'asc')
            ->get();
        $pdf = App::make('dompdf.wrapper');
        $pdfFile = $pdf->loadView('pdf.invoice', compact('invoice', 'vote'));
        $fullpath = 'pdf/invoice/invoice_' . $invoice->id . '.pdf';
        Storage::disk('local')->put($fullpath, $pdf->output());
        $url = Storage::disk('local')->url($fullpath);

        $invoice = Invoice::find($invoiceId);
        $invoice->pdf_url = $url;
        $invoice->save();
        return [
            'success' => true,
            'pdf_link_url' => $invoice->pdf_link_url
        ];
    }

    public function updateAddressStatus(Request $request, $id)
    {
        // Validator
        $validator = Validator::make($request->all(), [
            'address' => 'required|in:0,1',
        ]);
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'address must 0 or 1'
            ];
        }
        $user = ComplianceUser::find($id);
        if ($user) {
            $user->address = $request->address;
            $user->save();
            return [
                'success' => true
            ];
        } else {
            return [
                'success' => false,
                'message' => 'Not found user'
            ];
        }
    }

    public function createAddressPayment(Request $request)
    {
        $user = Auth::user();
        // Validator
        $validator = Validator::make($request->all(), [
            'user_id' => 'required',
            'cspr_address' => 'required',
        ]);
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Provide all the necessary information'
            ];
        }

        $checkUser = PaymentAddress::where('user_id', $request->user_id)->first();
        if ($checkUser) {
            return [
                'success' => false,
                'message' => 'User exist payment request',
            ];
        }
        $checkAddress = PaymentAddress::where('cspr_address', $request->cspr_address)->first();
        if ($checkAddress) {
            $userExist = User::find($checkAddress->user_id);
            return [
                'success' => false,
                'message' => "This address is already inuse by user $userExist->email",
            ];
        }
        $payment_address = new PaymentAddress();
        $payment_address->user_id = $request->user_id;
        $payment_address->compliance_user_id = $user->id;
        $payment_address->cspr_address = $request->cspr_address;
        $payment_address->save();

        $payment_address_change = new PaymentAddressChange();
        $payment_address_change->user_id = $request->user_id;
        $payment_address_change->cspr_address = $request->cspr_address;
        $payment_address_change->request_ip = request()->ip();
        $payment_address_change->status = 'approved';
        $payment_address_change->save();

        return [
            'success' => true
        ];
    }

    public function listUserVaPayment(Request $request)
    {
        $payment_address_users = PaymentAddress::pluck('user_id')->toArray();
        $users = User::where('users.is_admin', 0)
            ->where('users.is_guest', 0)
            ->where('can_access', 1)
            ->where('users.is_member', 1)
            ->whereNotIn('id', $payment_address_users)
            ->select([
                'users.id',
                'users.email',
            ])->get();
        return [
            'success' => true,
            'users' => $users,
        ];
    }

    public function confirmUpdateAddressPayment($id)
    {
        $user = Auth::user();
        $payment_address_change = PaymentAddressChange::where('id', $id)->where('status', 'pending')->first();
        if (!$payment_address_change) {
            return [
                'success' => false,
                'message' => 'Not found payment address change',
            ];
        }
        $payment_address_change->status = 'approved';
        $payment_address_change->approved_at = now();
        $payment_address_change->save();
        $payment_address = PaymentAddress::where('user_id', $payment_address_change->user_id)->first();
        if (!$payment_address) {
            $payment_address = new PaymentAddress();
        }
        $payment_address->user_id = $payment_address_change->user_id;
        $payment_address->compliance_user_id = $user->id;
        $payment_address->cspr_address = $payment_address_change->cspr_address;
        $payment_address->save();
        return [
            'success' => true,
        ];
    }

    public function voidAddressPayment($id)
    {
        $payment_address = PaymentAddressChange::where('id', $id)->where('status', 'pending')->first();
        if (!$payment_address) {
            return [
                'success' => false,
                'message' => 'Not found payment address',
            ];
        }
        $payment_address->delete();
        return [
            'success' => true,
        ];
    }

    public function getPendingAddressPayment(Request $request)
    {
        $user = Auth::user();
        // Variables
        $sort_key = $sort_direction = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'payment_address_change.id';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $start = $limit * ($page_id - 1);

        $payment_addresses = PaymentAddressChange::join('users', 'users.id', '=', 'payment_address_change.user_id')
            ->where('payment_address_change.status', 'pending')
            ->select([
                'payment_address_change.*',
                'users.email',
            ])
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();
        return [
            'success' => true,
            'payment_addresses' => $payment_addresses,
            'finished' => count($payment_addresses) < $limit ? true : false
        ];
    }

    public function getCurrentAddressPayment(Request $request)
    {
        $user = Auth::user();
        // Variables
        $sort_key = $sort_direction = '';
        $page_id = 0;
        $data = $request->all();
        if ($data && is_array($data)) extract($data);

        if (!$sort_key) $sort_key = 'payment_address.id';
        if (!$sort_direction) $sort_direction = 'desc';
        $page_id = (int) $page_id;
        if ($page_id <= 0) $page_id = 1;

        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $start = $limit * ($page_id - 1);

        $payment_addresses = PaymentAddress::with(['paymentAddressChanges'])
            ->join('users', 'users.id', '=', 'payment_address.user_id')
            ->select([
                'payment_address.*',
                'users.email',
            ])
            ->orderBy($sort_key, $sort_direction)
            ->offset($start)
            ->limit($limit)
            ->get();
        return [
            'success' => true,
            'payment_addresses' => $payment_addresses,
            'finished' => count($payment_addresses) < $limit ? true : false
        ];
    }

    public function getCurrentAddressPaymentUser()
    {
        $user = Auth::user();
        $payment_address = PaymentAddress::where('user_id', $user->id)->first();
        if (!$payment_address) {
            return [
                'success' => false,
                'message' => 'payment address not found'
            ];
        }
        return [
            'success' => true,
            'payment_address' => $payment_address
        ];
    }

    public function changePaymentAddress(Request $request)
    {
        $user = Auth::user();
        // Validator
        $validator = Validator::make($request->all(), [
            'cspr_address' => 'required',
        ]);
        if ($validator->fails()) {
            return [
                'success' => false,
                'message' => 'Provide all the necessary information'
            ];
        }
        $checkAddress = PaymentAddress::where('cspr_address', $request->cspr_address)->first();
        if ($checkAddress) {
            $userExist = User::find($checkAddress->user_id);
            return [
                'success' => false,
                'message' => "This address is already inuse by user $userExist->email",
            ];
        }
        $payment_address_change = PaymentAddressChange::where('user_id', $user->id)->orderBy('id', 'desc')->first();
        if (!$payment_address_change || $payment_address_change->status == 'approved') {
            $payment_address_change = new PaymentAddressChange();
        }
        $payment_address_change->user_id = $user->id;
        $payment_address_change->cspr_address = $request->cspr_address;
        $payment_address_change->request_ip = request()->ip();
        $payment_address_change->status = 'pending';
        $payment_address_change->save();
        return [
            'success' => true,
        ];
    }

    public function loginWithUserVa(Request $request)
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

        $user = User::where('email', $email)->first();
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Email does not exist'
            ];
        }

        if ($user->is_member == 1) {
            if (!Hash::check($password, $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Email or Password is not correct'
                ];
            }

            if ($user->status == 'revoked' || $user->banned == 1) {
                return [
                    'success' => false,
                    'message' => 'You are banned. Please contact us for further details.'
                ];
            }
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
}
