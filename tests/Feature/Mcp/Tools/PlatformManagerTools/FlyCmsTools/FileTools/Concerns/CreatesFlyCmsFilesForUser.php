<?php

namespace Tests\Feature\Mcp\Tools\PlatformManagerTools\FlyCmsTools\FileTools\Concerns;

use App\Contracts\PlatformManager\FlyCms\MutationData\FileMutationData\CreateFileData;
use App\Mcp\Tools\PlatformManagerTools\FlyCmsTools\WebsiteTools\ShowWebsiteTool;
use App\Models\Channel;
use App\Models\User;

trait CreatesFlyCmsFilesForUser
{
    private function createFlyCmsFileForUser(User $user, Channel $channel, array $createFileData = []): string
    {
        $channel = $channel->load('platform');
        $tool = new ShowWebsiteTool;
        $flycms = $tool->getFlyCmsManager($channel, $user);

        $created = $flycms->createFile(
            'binary-content',
            (new CreateFileData)->setData(array_merge(['ext' => 'png'], $createFileData))
        );

        return (string) $created->get('id');
    }
}
