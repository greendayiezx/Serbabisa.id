import L from 'leaflet'
import driverMarkerImg from '@/assets/driver-marker.svg'

export { driverMarkerImg }

export function driverPinIcon(): L.DivIcon {
  return L.divIcon({
    className: 'driver-pin',
    html: `<img class="driver-pin__img" src="${driverMarkerImg}" alt="" />`,
    iconSize: [56, 40],
    iconAnchor: [28, 38],
    popupAnchor: [0, -20],
  })
}