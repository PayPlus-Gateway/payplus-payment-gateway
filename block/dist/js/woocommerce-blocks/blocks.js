(() => {
  "use strict";
  const e = window.React,
    t = window.wc.wcBlocksRegistry,
    a = window.wp.i18n,
    p = window.wc.wcSettings,
    n = window.wp.htmlEntities,
    i = [
      "payplus-payment-gateway",
      "payplus-payment-gateway-bit",
      "payplus-payment-gateway-googlepay",
      "payplus-payment-gateway-applepay",
      "payplus-payment-gateway-multipass",
      "payplus-payment-gateway-tavzahav",
      "payplus-payment-gateway-valuecard",
      "payplus-payment-gateway-finitione",
      "payplus-payment-gateway-paypal",
    ],
    s = (e) => (0, n.decodeEntities)(e.description || ""),
    y = (t) => {
      const { PaymentMethodLabel: a } = t.components;
      return (0, e.createElement)(
        "div",
        { className: "payplus-method" },
        (0, e.createElement)(a, {
          text: t.text,
          icon: t.icon
            ? (0, e.createElement)("img", {
                style: {
                  width: "64px",
                  height: "32px",
                  maxHeight: "100%",
                  margin: "0px 10px",
                },
                src: t.icon,
              })
            : "",
        }),
        (0, e.createElement)("div", { className: "pp_iframe" })
      );
    };
  (() => {
    for (let c = 0; c < i.length; c++) {
      const l = i[c],
        o = (0, p.getPaymentMethodData)(l, {}),
        m = (0, a.__)(
          "Pay with Debit or Credit Card",
          "payplus-payment-gateway"
        ),
        r = (0, n.decodeEntities)(o?.title || "") || m,
        w = {
          name: l,
          label: (0, e.createElement)(y, { text: r, icon: o.icon }),
          content: (0, e.createElement)(s, { description: o.description }),
          edit: (0, e.createElement)(s, { description: o.description }),
          canMakePayment: () => !0,
          ariaLabel: r,
          supports: {
            showSaveOption: o.showSaveOption,
            features: o.supports,
          },
        };
      (0, t.registerPaymentMethod)(w);
    }
  })();
})();

var checkExist = setInterval(function () {
  if (
    document.querySelector(
      ".wc-block-components-checkbox.wc-block-components-payment-methods__save-card-info"
    )
  ) {
    clearInterval(checkExist);
    var saveCardInfo = document.querySelector(
      ".wc-block-components-checkbox__label"
    ).textContent;

    if (document.documentElement.lang === "he-IL") {
      document.querySelector(
        ".wc-block-components-checkbox__label"
      ).textContent = "שמירת פרטי התשלום בחשבון שלי, לרכישות עתידיות";
    }
  }
}, 100); // check every 100ms
