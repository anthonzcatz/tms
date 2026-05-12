import utils from './utils';
/*-----------------------------------------------
|   Gooogle Map
-----------------------------------------------*/

function destroyMap(map) {
  if (map) {
    window.google.maps.event.clearInstanceListeners(map);
    map = null;
  }
}

function initMap() {
  const themeController = document.body;
  const $googlemaps = document.querySelectorAll('.googlemap');

  if ($googlemaps.length && window.google) {

    $googlemaps.forEach(async (itm) => {
      const { Map, InfoWindow } = await window.google.maps.importLibrary('maps');
      const { AdvancedMarkerElement } = await window.google.maps.importLibrary('marker');
      const { ColorScheme } = await window.google.maps.importLibrary('core');

      const latLng = utils.getData(itm, 'latlng').split(',');
      const markerPopup = itm.innerHTML;
      const zoom = utils.getData(itm, 'zoom');
      const mapId = utils.getData(itm, 'mapid');
      const mapElement = itm;

      const lightIconUrl = utils.getData(itm, 'icon')
        ? utils.getData(itm, 'icon')
        : 'https://maps.gstatic.com/mapfiles/api-3/images/spotlight-poi.png';

      const darkIconUrl = utils.getData(itm, 'dark-icon')
        ? utils.getData(itm, 'dark-icon')
        : lightIconUrl; // Fallback

      if (utils.getData(itm, 'theme') === 'streetview') {
        const pov = utils.getData(itm, 'pov');

        const mapOptions = {
          position: { lat: Number(latLng[0]), lng: Number(latLng[1]) },
          pov,
          zoom,
          gestureHandling: 'none',
          scrollwheel: false,
          linksControl: true,
          panControl: true,
          motionTracking: false,
          visible: true,
        };

        return new window.google.maps.StreetViewPanorama(mapElement, mapOptions);
      }

      const mapOptions = {
        mapId,
        zoom,
        scrollwheel: utils.getData(itm, 'scrollwheel'),
        center: { lat: Number(latLng[0]), lng: Number(latLng[1]) },
        colorScheme: utils.isDark() === 'dark' ? ColorScheme.DARK : ColorScheme.LIGHT,
      };

      const map = new Map(mapElement, mapOptions);
      const infowindow = new InfoWindow({
        content: markerPopup
      });

      const iconImage = document.createElement('img');
      iconImage.src = utils.isDark() === 'dark' ? darkIconUrl : lightIconUrl;

      const marker = new AdvancedMarkerElement({
        position: { lat: Number(latLng[0]), lng: Number(latLng[1]) },
        content: iconImage,
        map
      });

      marker.addListener('click', () => {
        infowindow.open(map, marker);
      });

      themeController &&
        themeController.addEventListener('clickControl', ({ detail: { control } }) => {
          if (control === 'theme') {
            destroyMap(map);
            initMap();
          }
        });
      return null;
    });
  }
}

export default initMap;
