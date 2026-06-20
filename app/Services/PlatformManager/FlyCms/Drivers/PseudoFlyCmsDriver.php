<?php

namespace App\Services\PlatformManager\FlyCms\Drivers;

use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsArticles;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsDomains;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsFiles;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsMenus;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsMeta;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsPages;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsPosts;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsRoles;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsTags;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsThemes;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsUsers;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\InteractsWithPseudoFlyCmsWebsites;
use App\Services\PlatformManager\FlyCms\Drivers\PseudoConcerns\ManagesPseudoFlyCmsStore;
use App\Services\PlatformManager\FlyCms\FlyCmsService;

class PseudoFlyCmsDriver extends FlyCmsService
{
    use InteractsWithPseudoFlyCmsArticles;
    use InteractsWithPseudoFlyCmsDomains;
    use InteractsWithPseudoFlyCmsFiles;
    use InteractsWithPseudoFlyCmsMenus;
    use InteractsWithPseudoFlyCmsMeta;
    use InteractsWithPseudoFlyCmsPages;
    use InteractsWithPseudoFlyCmsPosts;
    use InteractsWithPseudoFlyCmsRoles;
    use InteractsWithPseudoFlyCmsTags;
    use InteractsWithPseudoFlyCmsThemes;
    use InteractsWithPseudoFlyCmsUsers;
    use InteractsWithPseudoFlyCmsWebsites;
    use ManagesPseudoFlyCmsStore;
}
