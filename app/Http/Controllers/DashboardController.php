<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Media;
use App\Models\Face;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
public function index() {
    // MySQL usa LIKE em vez de ILIKE
    $stats = Media::select('media_gallery', 
                DB::raw('count(*) as total'),
                DB::raw("sum(case when mime_type LIKE 'video%' then 1 else 0 end) as videos"),
                DB::raw("sum(case when mime_type LIKE 'image%' then 1 else 0 end) as images")
             )->groupBy('media_gallery')->get();

    $recentImages = Media::orderBy('id', 'desc')->take(20)->get();
    
    // Tratativa para evitar erro se a tabela faces não existir ou estiver vazia
    $faces = Face::orderBy('id', 'desc')->paginate(24);
    
    $totalSize = Media::sum('file_size') ?? 0;

    return view('dashboard', compact('stats', 'recentImages', 'faces', 'totalSize'));
}
}