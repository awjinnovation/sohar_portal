/**
 * Bootstrap Icons (MIT) used by the site, bundled as inline SVG: only these
 * ship, with no icon font or CDN request. <Icon name="ticket-perforated" />
 * renders <i class="bi"> so the shared styles (public.css) size and color it.
 */
import i_ticketPerforated from 'bootstrap-icons/icons/ticket-perforated.svg?raw';
import i_calendar3 from 'bootstrap-icons/icons/calendar3.svg?raw';
import i_clock from 'bootstrap-icons/icons/clock.svg?raw';
import i_geoAlt from 'bootstrap-icons/icons/geo-alt.svg?raw';
import i_chatDots from 'bootstrap-icons/icons/chat-dots.svg?raw';
import i_personCheck from 'bootstrap-icons/icons/person-check.svg?raw';
import i_checkCircle from 'bootstrap-icons/icons/check-circle.svg?raw';
import i_infoCircle from 'bootstrap-icons/icons/info-circle.svg?raw';
import i_xCircle from 'bootstrap-icons/icons/x-circle.svg?raw';
import i_exclamationCircle from 'bootstrap-icons/icons/exclamation-circle.svg?raw';
import i_clockHistory from 'bootstrap-icons/icons/clock-history.svg?raw';
import i_shieldExclamation from 'bootstrap-icons/icons/shield-exclamation.svg?raw';
import i_hourglassSplit from 'bootstrap-icons/icons/hourglass-split.svg?raw';
import i_calendarX from 'bootstrap-icons/icons/calendar-x.svg?raw';
import i_xOctagon from 'bootstrap-icons/icons/x-octagon.svg?raw';
import i_lock from 'bootstrap-icons/icons/lock.svg?raw';
import i_qrCode from 'bootstrap-icons/icons/qr-code.svg?raw';
import i_people from 'bootstrap-icons/icons/people.svg?raw';
import i_personVcard from 'bootstrap-icons/icons/person-vcard.svg?raw';
import i_download from 'bootstrap-icons/icons/download.svg?raw';
import i_phone from 'bootstrap-icons/icons/phone.svg?raw';
import i_send from 'bootstrap-icons/icons/send.svg?raw';
import i_shieldLock from 'bootstrap-icons/icons/shield-lock.svg?raw';
import i_boxArrowInRight from 'bootstrap-icons/icons/box-arrow-in-right.svg?raw';
import i_arrowRepeat from 'bootstrap-icons/icons/arrow-repeat.svg?raw';
import i_pencil from 'bootstrap-icons/icons/pencil.svg?raw';
import i_cart from 'bootstrap-icons/icons/cart.svg?raw';
import i_dash from 'bootstrap-icons/icons/dash.svg?raw';
import i_plus from 'bootstrap-icons/icons/plus.svg?raw';
import i_creditCard2front from 'bootstrap-icons/icons/credit-card-2-front.svg?raw';
import i_personCircle from 'bootstrap-icons/icons/person-circle.svg?raw';
import i_translate from 'bootstrap-icons/icons/translate.svg?raw';
import i_calendar2heart from 'bootstrap-icons/icons/calendar2-heart.svg?raw';
import i_shieldCheck from 'bootstrap-icons/icons/shield-check.svg?raw';
import i_fileEarmarkText from 'bootstrap-icons/icons/file-earmark-text.svg?raw';
import i_envelope from 'bootstrap-icons/icons/envelope.svg?raw';
import i_telephone from 'bootstrap-icons/icons/telephone.svg?raw';
import i_wifiOff from 'bootstrap-icons/icons/wifi-off.svg?raw';

const ICONS = {
  'ticket-perforated': i_ticketPerforated,
  'calendar3': i_calendar3,
  'clock': i_clock,
  'geo-alt': i_geoAlt,
  'chat-dots': i_chatDots,
  'person-check': i_personCheck,
  'check-circle': i_checkCircle,
  'info-circle': i_infoCircle,
  'x-circle': i_xCircle,
  'exclamation-circle': i_exclamationCircle,
  'clock-history': i_clockHistory,
  'shield-exclamation': i_shieldExclamation,
  'hourglass-split': i_hourglassSplit,
  'calendar-x': i_calendarX,
  'x-octagon': i_xOctagon,
  'lock': i_lock,
  'qr-code': i_qrCode,
  'people': i_people,
  'person-vcard': i_personVcard,
  'download': i_download,
  'phone': i_phone,
  'send': i_send,
  'shield-lock': i_shieldLock,
  'box-arrow-in-right': i_boxArrowInRight,
  'arrow-repeat': i_arrowRepeat,
  'pencil': i_pencil,
  'cart': i_cart,
  'dash': i_dash,
  'plus': i_plus,
  'credit-card-2-front': i_creditCard2front,
  'person-circle': i_personCircle,
  'translate': i_translate,
  'calendar2-heart': i_calendar2heart,
  'shield-check': i_shieldCheck,
  'file-earmark-text': i_fileEarmarkText,
  'envelope': i_envelope,
  'telephone': i_telephone,
  'wifi-off': i_wifiOff,
} as const;

export type IconName = keyof typeof ICONS;

export function Icon({ name, className }: { name: IconName; className?: string }) {
  return <i className={className ? `bi ${className}` : 'bi'} aria-hidden="true" dangerouslySetInnerHTML={{ __html: ICONS[name] }} />;
}
