<?php

namespace App\Http\Controllers;

use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use Livewire\Mechanisms\HandleRequests\HandleRequests;
use Livewire\Features\SupportFileUploads\FileUploadController;
use Livewire\Features\SupportFileUploads\FilePreviewController;

class LivewireAssetController extends Controller
{
    /**
     * Serve Livewire JavaScript bundle.
     */
    public function script()
    {
        return app(FrontendAssets::class)->returnJavaScriptAsFile();
    }

    /**
     * Serve Livewire JavaScript Source Map.
     */
    public function maps()
    {
        return app(FrontendAssets::class)->maps();
    }

    /**
     * Handle Livewire component update requests.
     */
    public function update()
    {
        return app(HandleRequests::class)->handleUpdate();
    }

    /**
     * Handle Livewire file upload requests.
     */
    public function upload()
    {
        return app(FileUploadController::class)->handle();
    }

    /**
     * Handle Livewire file preview requests.
     */
    public function preview($filename)
    {
        return app(FilePreviewController::class)->handle($filename);
    }
}
