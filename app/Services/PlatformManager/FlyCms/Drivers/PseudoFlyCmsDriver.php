<?php

namespace App\Services\PlatformManager\FlyCms\Drivers;

use App\Services\PlatformManager\FlyCms\Drivers\Concerns\InteractsWithPseudoFlyCmsDomains;
use App\Services\PlatformManager\FlyCms\Drivers\Concerns\InteractsWithPseudoFlyCmsFiles;
use App\Services\PlatformManager\FlyCms\Drivers\Concerns\InteractsWithPseudoFlyCmsMenus;
use App\Services\PlatformManager\FlyCms\Drivers\Concerns\InteractsWithPseudoFlyCmsPages;
use App\Services\PlatformManager\FlyCms\Drivers\Concerns\InteractsWithPseudoFlyCmsPosts;
use App\Services\PlatformManager\FlyCms\Drivers\Concerns\InteractsWithPseudoFlyCmsRoles;
use App\Services\PlatformManager\FlyCms\Drivers\Concerns\InteractsWithPseudoFlyCmsTags;
use App\Services\PlatformManager\FlyCms\Drivers\Concerns\InteractsWithPseudoFlyCmsThemes;
use App\Services\PlatformManager\FlyCms\Drivers\Concerns\InteractsWithPseudoFlyCmsUsers;
use App\Services\PlatformManager\FlyCms\Drivers\Concerns\InteractsWithPseudoFlyCmsWebsites;
use App\Services\PlatformManager\FlyCms\Drivers\Concerns\ManagesPseudoFlyCmsStore;
use App\Services\PlatformManager\FlyCms\FlyCmsService;

class PseudoFlyCmsDriver extends FlyCmsService
{
    use InteractsWithPseudoFlyCmsDomains;
    use InteractsWithPseudoFlyCmsFiles;
    use InteractsWithPseudoFlyCmsMenus;
    use InteractsWithPseudoFlyCmsPages;
    use InteractsWithPseudoFlyCmsPosts;
    use InteractsWithPseudoFlyCmsRoles;
    use InteractsWithPseudoFlyCmsTags;
    use InteractsWithPseudoFlyCmsThemes;
    use InteractsWithPseudoFlyCmsUsers;
    use InteractsWithPseudoFlyCmsWebsites;
    use ManagesPseudoFlyCmsStore;
}
