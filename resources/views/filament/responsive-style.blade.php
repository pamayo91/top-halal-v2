<style id="fi-admin-responsive">
    .fi-main { max-width: 100%; min-width: 0; }
    .fi-main-ctn, .fi-page, .fi-section, .fi-ta { min-width: 0; }
    .restaurant-hours-slots.fi-fo-repeater { display: grid; grid-template-columns: max-content 9rem; align-items: center; column-gap: .5rem; width: max-content; max-width: 100%; }
    .restaurant-hours-slots .fi-fo-repeater-items, .restaurant-hours-slots .fi-fo-repeater-item { display: contents; }
    .restaurant-hours-slots .fi-fo-repeater-item-content { grid-column: 1; padding: 0; width: max-content; }
    .restaurant-hours-slots .fi-fo-repeater-item-has-header > .fi-fo-repeater-item-content { border-top: 0; padding-right: 0; }
    .restaurant-hours-slots .fi-fo-repeater-item-header { position: static; grid-column: 2; align-self: center; justify-content: flex-start; padding: 0; width: 9rem; }
    .restaurant-hours-slots .fi-fo-repeater-item-header-end-actions { gap: 0; margin-left: 0; }
    .restaurant-hours-slots .fi-fo-field, .restaurant-hours-slots .fi-fo-field-wrp { gap: 0; }
    .restaurant-hours-slots .fi-sc { display: grid; grid-template-columns: max-content max-content; gap: .5rem; width: max-content; }
    .restaurant-hours-slots .fi-input-wrp { min-height: 2rem; width: 17rem; }
    .restaurant-hours-slots .fi-input { min-height: 2rem; width: 7.25rem; padding: .25rem .5rem; font-size: .8125rem; }
    .restaurant-hours-slots .fi-input-wrp-prefix { gap: .25rem; padding-inline: .5rem; font-size: .75rem; }
    .restaurant-hours-slots .fi-fo-repeater-add { grid-column: 2; grid-row: 1; width: 9rem; justify-content: flex-start; align-self: center; margin: 0; }

    @media (max-width: 767px) {
        .fi-main { padding-inline: .75rem; }
        .fi-page-header-main-ctn, .fi-ta-header-ctn, .fi-ta-header-toolbar, .fi-ta-header-toolbar-ctn { min-width: 0; flex-wrap: wrap; }
        .fi-ta-header-toolbar-actions, .fi-ta-header-toolbar .fi-input-wrp { width: 100%; max-width: none; }
        .fi-ta-content { max-width: 100%; }
    }
</style>
