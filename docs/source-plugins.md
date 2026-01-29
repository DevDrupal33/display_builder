# Extend Display Builder with source plugins

Source plugins are managed by UI Patterns 2. See [UI Patterns 2 documentation](https://project.pages.drupalcode.org/ui_patterns/3-devs/1-source-plugins/).

## Source plugin for slots

A source plugin targeting `slot` prop type:

```php
namespace Drupal\my_module\Plugin\UiPatterns\Source;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ui_patterns\Attribute\Source;
use Drupal\ui_patterns\SourcePluginBase;

#[Source(
  id: 'my_source',
  label: new TranslatableMarkup('My slot source'),
  prop_types: ['slot']
)]
class MySource extends SourcePluginBase {
}
```

.. will show up in the _Block Library_ panel:

![Source slots](images/source-slots.webp)

Source configuration from `PluginSettingsInterface::settingsForm()` is available in the contextual sidebar. For example:

![Source slots configuration](images/sources-slot-config.webp)

### Source plugins for props

They are displayed in the component form managed by UI Patterns 2. Display Builder is doing nothing specific here.
