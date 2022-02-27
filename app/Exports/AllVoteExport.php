<?php

namespace App\Exports;

use App\User;
use Illuminate\Contracts\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;

class AllVoteExport implements FromView, WithEvents, ShouldAutoSize
{
    use RegistersEventListeners;
    protected $votes ;
    protected $users ;

    public function __construct($votes, $users)
    {
        $this->votes = $votes;
        $this->users = $users;
    }
    public function view(): View
    {
        return view('excel.all_vote', [
            'votes' => $this->votes,
            'users' => $this->users,
        ]);
    }

}
