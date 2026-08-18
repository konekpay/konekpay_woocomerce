const settings = window.wc.wcSettings.getSetting( 'konekpay_data', {} );
const label = window.wp.htmlEntities.decodeEntities( settings.title ) || 'Konekpay';
const Content = () => {
    return window.wp.htmlEntities.decodeEntities( settings.description || '' );
};
const Konekpay = {
    name: 'konekpay',
    label: label,
    content: window.wp.element.createElement( Content, null ),
    edit: window.wp.element.createElement( Content, null ),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports || [],
    },
};
window.wc.wcBlocksRegistry.registerPaymentMethod( Konekpay );
