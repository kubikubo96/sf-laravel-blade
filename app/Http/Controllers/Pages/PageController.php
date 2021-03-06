<?php

namespace App\Http\Controllers\Pages;

use App\Http\Controllers\Controller;
use App\Repositories\NotificationRepository;
use App\Repositories\UserRepository;
use App\Repositories\PostRepository;
use App\Services\ElasticsearchService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PageController extends Controller
{
    private $userRepository;
    private $postRepository;
    private $elasticService;
    private $notyRepo;

    public function __construct(NotificationRepository $notyRepo, UserRepository $userRepository, PostRepository $postRepository, ElasticsearchService $elasticService)
    {
        $this->userRepository = $userRepository;
        $this->postRepository = $postRepository;
        $this->elasticService = $elasticService;
        $this->notyRepo = $notyRepo;
    }

    function homepage()
    {
        $post = $this->postRepository->postPaginate();
        $first_news = $this->postRepository->firstNews();
        $second_news = $this->postRepository->secondNews();
        $notifications = $this->notyRepo->getAll();

        return view('pages.index', ['post' => $post, 'first_news' => $first_news, 'second_news' => $second_news, 'notifications' => $notifications]);
    }

    public function search(Request $request)
    {
        $keyword = $request->input('key', '');
        $post = $this->elasticService->search('my_post',$keyword);

        return view('pages.search', ['post' => $post['data']]);
    }

    function getLogin()
    {
        return view('pages.appcrud.login');
    }

    function postLogin(Request $request)
    {
        $this->validate(
            $request,
            [
                'email' => 'required',
                'password' => 'required'
            ],
            [
                'email.required' => 'Bạn chưa nhập Email',
                'password.required' => 'Bạn chưa nhập Password'
            ]
        );
        //Auth::attempt :  kiểm tra đăng nhập
        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return redirect('/');
        } else {
            return redirect('login')->with('notify', 'Đăng nhập không thành công :(');
        }
    }

    function getLogout()
    {
        Auth::logout();
        return redirect('/');
    }

    function getRegister()
    {
        return view('pages\appcrud\register');
    }

    function postRegister(Request $request)
    {
        $this->validate(
            $request,
            [
                'name' => "required",
                'email' => 'required|email|unique:users,email',
                'password' => 'required',
                'confirm_password' => 'required|same:password'
            ],
            [
                'name.required' => 'Bạn chưa nhập tên người dùng',
                'email.required' => 'Bạn chưa nhập email',
                'email.email' => 'Bạn chưa nhập đúng định dạng email',
                'email.unique' => 'Email đã tồn tại',
                'password.required' => 'Bạn chưa nhập password',
                'confirm_password.required' => 'Bạn chưa nhập lại mật khẩu',
                'confirm_password.same' => 'Mật khẩu nhập lại chưa khớp'
            ]
        );

        $this->userRepository->create($request);

        return redirect('register')->with('notify', 'Đăng ký thành công !');
    }

    function getUserPersonal($id)
    {
        $user = $this->userRepository->find($id);
        return view('pages.appcrud.edit', ['user' => $user]);
    }

    function postUserPersonal(Request $request, $id)
    {
        $this->validate(
            $request,
            [
                'name' => "required",
            ],
            [
                'name.required' => 'Bạn chưa nhập tên người dùng',
            ]
        );
        $user = $this->userRepository->find($id);

        $user->name = $request->name;

        if ($request->changePassword == "on") {
            $this->validate(
                $request,
                [
                    'password' => 'required',
                    'confirm_password' => 'required|same:password'
                ],
                [
                    'password.required' => 'Bạn chưa nhập password',
                    'confirm_password.required' => 'Bạn chưa nhập lại mật khẩu',
                    'confirm_password.same' => 'Mật khẩu nhập lại chưa khớp'
                ]
            );
            $user->password = bcrypt($request->password);
        }
        $user->save();

        return redirect('user_personal/' . $id)->with('notify', 'Bạn đã sữa thành công');
    }

    function getDetail($id)
    {
        $post = $this->postRepository->find($id);
        $notifications = $this->notyRepo->getAll();
        return view('pages.detail', ['post' => $post, 'notifications' => $notifications]);
    }

    public function getForgotPassword()
    {
        return view('pages.appcrud.forgot_password');
    }

    public function postForgotPassword(Request $request)
    {
        $this->validate($request, [
            'email' => 'required'
        ], [
            'email.required' => ' Email không được để trống !'
        ]);
        $yourMail = $request->email;
        $yourUser = DB::table('users')->where('email', $yourMail)->first();
        $yourID = $yourUser->id;
        $user = $this->userRepository->find($yourID);
        $passwordReset = str::random(10);
        $user->password = bcrypt($passwordReset);
        $user->save();

        $details = [
            'title' => 'Hãy đăng nhập và đổi mật khẩu ngay sau đó :)))',
            'body' => 'Password của ' . $yourMail . ' là : ' . $passwordReset,
        ];

        \Mail::to($yourMail)->send(new \App\Mail\MyTestMail($details));

        return redirect('login')->with('notifySuccess', 'Check mail của bạn và đăng nhập bằng mật khẩu mới !!');
    }
}
