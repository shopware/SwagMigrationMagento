/**
 * @deprecated tag:v14.0.0 - With Shopware v6.8.0 - `translation.init.ts` will be removed to use automatic language loading with language layer support
 */
import deMagentoSnippets from '../app/snippet/de.json';
import enMagentoSnippets from '../app/snippet/en.json';

Shopware.Locale.extend('de-DE', deMagentoSnippets);
Shopware.Locale.extend('en-GB', enMagentoSnippets);
