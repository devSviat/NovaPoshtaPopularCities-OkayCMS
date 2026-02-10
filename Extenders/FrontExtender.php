<?php


namespace Okay\Modules\Sviat\NovaPoshtaPopularCities\Extenders;


use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtenderFacade;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshtaPopularCities\Entities\NPPopularCitiesEntity;

class FrontExtender implements ExtensionInterface
{
    private EntityFactory $entityFactory;
    private Design $design;
    private Settings $settings;
    private ?int $npModuleId = null;
    private const NP_MODULE_NAMESPACE = 'Okay\Modules\OkayCMS\NovaposhtaCost\Extenders\FrontExtender';

    public function __construct(
        EntityFactory $entityFactory,
        Design $design,
        Settings $settings
    ) {
        $this->entityFactory = $entityFactory;
        $this->design = $design;
        $this->settings = $settings;
    }

    private function getNpModuleId(): ?int
    {
        if ($this->npModuleId === null) {
            $SL = \Okay\Core\ServiceLocator::getInstance();
            $module = $SL->getService(\Okay\Core\Modules\Module::class);
            $this->npModuleId = $module->getModuleIdByNamespace(self::NP_MODULE_NAMESPACE) ?: false;
        }
        return $this->npModuleId ?: null;
    }

    public function assignPopularCities($deliveries, $cart)
    {
        $npModuleId = $this->getNpModuleId();
        if (!$npModuleId || empty($deliveries)) {
            return ExtenderFacade::execute(__METHOD__, $deliveries, func_get_args());
        }
        
        foreach ($deliveries as $delivery) {
            if ($delivery->module_id == $npModuleId) {
                $popularCitiesEntity = $this->entityFactory->get(NPPopularCitiesEntity::class);
                $popularCities = $popularCitiesEntity->findWithNames();
                if (!empty($popularCities)) {
                    $this->design->assign('np_popular_cities', $popularCities);
                    $this->design->assign('np_delivery_module_id', $npModuleId);
                    
                    $enableIpDetection = $this->settings->get('sviat__np_popular_cities__enable_ip_detection');
                    $this->design->assign('np_enable_ip_detection', $enableIpDetection ? 1 : 0);
                }
                break;
            }
        }
        
        return ExtenderFacade::execute(__METHOD__, $deliveries, func_get_args());
    }
}
