<?php

namespace App\Http\Controllers;

use App\Models\JobOffer;
use Illuminate\Http\Request;
use App\Models\Occupation;
use App\Http\Requests\JobOfferRequest;
use Illuminate\Support\Facades\DB;
use App\Models\JobOfferView;
use Illuminate\Support\Facades\Auth;
use App\Consts\UserConst;
use Illuminate\Support\Str;
use App\Consts\CompanyConst;
use App\Models\Message;

class JobOfferController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = '';
        foreach (config('fortify.users') as $guard) {
            if (Auth::guard(Str::plural($guard))->check()) {
                $user = Auth::guard(Str::plural($guard))->user();
            }
        }

        if (empty($user)) {
            return view('welcome');
        } else {
            $params = $request->query();
            $jobOffers = JobOffer::search($params)->openData()
                ->order($params)->with(['company', 'occupation'])->latest()->paginate(5);
            $occupation = $request->occupation;

            $search_occupation = empty($occupation) ? [] : ['occupation' => $occupation];
            $sort = empty($request->sort) ? [] : ['sort' => $request->sort];

            $jobOffers->appends(compact('occupation'));
            $occupations = Occupation::all();
            return view(
                'job_offers.index',
                compact(
                    'jobOffers',
                    'occupations',
                    'search_occupation',
                    'sort'
                )
            );
        }
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $occupations = Occupation::all();
        return view('job_offers.create', compact('occupations'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \App\Http\Requests\JobOfferRequest  $request
     * @return \Illuminate\Http\Response
     */
    public function store(JobOfferRequest $request)
    {
        $jobOffer = new JobOffer($request->all());
        $jobOffer->company_id = $request->user()->id;

        // トランザクション開始
        DB::beginTransaction();
        try {
            // 登録
            $jobOffer->save();

            // トランザクション終了(成功)
            DB::commit();
        } catch (\Exception $e) {
            // トランザクション終了(失敗)
            DB::rollback();
            return back()->withInput()
                ->withErrors('求人情報登録処理でエラーが発生しました');
        }

        return redirect()
            ->route('job_offers.show', $jobOffer)
            ->with('notice', '求人情報を登録しました');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\JobOffer  $jobOffer
     * @return \Illuminate\Http\Response
     */
    public function show(JobOffer $jobOffer)
    {

        $entry = '';
        $entries = [];
        $messages = [];

        if (Auth::guard(UserConst::GUARD)->check()) {
            JobOfferView::updateOrCreate([
                'job_offer_id' => $jobOffer->id,
                'user_id' => Auth::guard(UserConst::GUARD)->user()->id,
            ]);
            $entry = $jobOffer->entries()
                ->where('user_id', Auth::guard(UserConst::GUARD)->user()->id)->first();
        }

        if (
            Auth::guard(CompanyConst::GUARD)->check() &&
            Auth::guard(CompanyConst::GUARD)->user()->id == $jobOffer->company_id
        ) {
            $entries = $jobOffer->entries()->with('user')->get();
        }

        return view('job_offers.show', compact('jobOffer', 'entry', 'entries', 'messages'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\JobOffer  $jobOffer
     * @return \Illuminate\Http\Response
     */
    public function edit(JobOffer $jobOffer)
    {
        $occupations = Occupation::all();
        return view('job_offers.edit', compact('jobOffer', 'occupations'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\JobOfferRequest  $request
     * @param  \App\Models\JobOffer  $jobOffer
     * @return \Illuminate\Http\Response
     */
    public function update(JobOfferRequest $request, JobOffer $jobOffer)
    {
        if (Auth::guard(CompanyConst::GUARD)->user()->cannot('update', $jobOffer)) {
            return redirect()->route('job_offers.show', $jobOffer)
                ->withErrors('自分の求人情報以外は更新できません');
        }
        $jobOffer->fill($request->all());

        // トランザクション開始
        DB::beginTransaction();
        try {
            $jobOffer->save();

            // トランザクション終了(成功)
            DB::commit();
        } catch (\Exception $e) {
            // トランザクション終了(失敗)
            DB::rollback();
            return back()->withInput()
                ->withErrors('求人情報更新処理でエラーが発生しました');
        }

        return redirect()->route('job_offers.show', $jobOffer)
            ->with('notice', '求人情報を更新しました');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\JobOffer  $jobOffer
     * @return \Illuminate\Http\Response
     */
    public function destroy(JobOffer $jobOffer)
    {
        if (Auth::guard(CompanyConst::GUARD)->user()->cannot('delete', $jobOffer)) {
            return redirect()->route('job_offers.show', $jobOffer)
                ->withErrors('自分の求人情報以外は削除できません');
        }
        // トランザクション開始
        DB::beginTransaction();
        try {
            $jobOffer->delete();
            db::commit();
        } catch (\Exception $e) {
            // トランザクション終了(失敗)
            DB::rollback();
            return back()->withInput()
                ->withErrors('求人情報削除処理でエラーが発生しました');
        }

        return redirect()->route('job_offers.index', $jobOffer)
            ->with('notice', '求人情報を削除しました');
    }
}
