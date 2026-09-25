export const calculatePricePreview = (ticketType, requestedQuantity, show) => {
  const unitPrice = Number(ticketType.price ?? 0);
  const subtotalAmount = unitPrice * requestedQuantity;
  let paidQuantity = requestedQuantity;
  let discountAmount = 0;

  if (ticketType.promotion) {
    if (ticketType.promotion.type === 'percent_discount') {
      discountAmount = subtotalAmount * Number(ticketType.promotion.value ?? 0) / 100;
    }

    if (ticketType.promotion.type === 'fixed_discount') {
      discountAmount = Number(ticketType.promotion.value ?? 0) * requestedQuantity;
    }

    if (ticketType.promotion.type === 'buy_x_get_y') {
      const bundleQuantity = Number(ticketType.promotion.bundle_quantity ?? 0);
      const payQuantity = Number(ticketType.promotion.pay_quantity ?? 0);

      if (bundleQuantity > 0 && payQuantity > 0) {
        paidQuantity = (
          Math.floor(requestedQuantity / bundleQuantity) * payQuantity
        ) + (requestedQuantity % bundleQuantity);
        discountAmount = subtotalAmount - (unitPrice * paidQuantity);
      }
    }
  }

  discountAmount = Math.min(subtotalAmount, discountAmount);
  const serviceFeeBaseAmount = subtotalAmount - discountAmount;
  let serviceFeeTotalAmount = show.service_fee_type === 'percentage'
    ? serviceFeeBaseAmount * Number(show.service_fee_percentage ?? 0) / 100
    : Number(show.service_fee_fixed_amount ?? 0) * paidQuantity;
  const serviceFeeMinimumUnitAmount = Number(show.service_fee_minimum_unit_amount ?? 0);
  const minimumServiceFeeTotalAmount = serviceFeeMinimumUnitAmount * paidQuantity;
  const serviceFeeMinimumApplied = serviceFeeBaseAmount > 0
    && serviceFeeMinimumUnitAmount > 0
    && serviceFeeTotalAmount < minimumServiceFeeTotalAmount;

  if (serviceFeeMinimumApplied) {
    serviceFeeTotalAmount = minimumServiceFeeTotalAmount;
  }

  return {
    paid_quantity: paidQuantity,
    unit_price: unitPrice,
    unit_service_fee: paidQuantity > 0 ? serviceFeeTotalAmount / paidQuantity : 0,
    service_fee_type: show.service_fee_type,
    service_fee_fixed_amount: show.service_fee_fixed_amount,
    service_fee_percentage: show.service_fee_percentage,
    service_fee_base_amount: serviceFeeBaseAmount,
    service_fee_minimum_applied: serviceFeeMinimumApplied,
    service_fee_minimum_unit_amount: show.service_fee_minimum_unit_amount,
    subtotal_amount: subtotalAmount,
    discount_amount: discountAmount,
    service_fee_total_amount: serviceFeeTotalAmount,
    total_amount: serviceFeeBaseAmount + serviceFeeTotalAmount,
  };
};
