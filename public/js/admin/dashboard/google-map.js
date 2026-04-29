var styles =[
  {
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#f5f5f5"
      }
    ]
  },
  {
    "elementType": "labels.icon",
    "stylers": [
      {
        "visibility": "off"
      }
    ]
  },
  {
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#616161"
      }
    ]
  },
  {
    "elementType": "labels.text.stroke",
    "stylers": [
      {
        "color": "#f5f5f5"
      }
    ]
  },
  {
    "featureType": "administrative.land_parcel",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#bdbdbd"
      }
    ]
  },
  {
    "featureType": "poi",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#eeeeee"
      }
    ]
  },
  {
    "featureType": "poi",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#757575"
      }
    ]
  },
  {
    "featureType": "poi.park",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#e5e5e5"
      }
    ]
  },
  {
    "featureType": "poi.park",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#9e9e9e"
      }
    ]
  },
  {
    "featureType": "road",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#ffffff"
      }
    ]
  },
  {
    "featureType": "road.arterial",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#757575"
      }
    ]
  },
  {
    "featureType": "road.highway",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#dadada"
      }
    ]
  },
  {
    "featureType": "road.highway",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#616161"
      }
    ]
  },
  {
    "featureType": "road.local",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#9e9e9e"
      }
    ]
  },
  {
    "featureType": "transit.line",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#e5e5e5"
      }
    ]
  },
  {
    "featureType": "transit.station",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#eeeeee"
      }
    ]
  },
  {
    "featureType": "water",
    "elementType": "geometry",
    "stylers": [
      {
        "color": "#c9c9c9"
      }
    ]
  },
  {
    "featureType": "water",
    "elementType": "labels.text.fill",
    "stylers": [
      {
        "color": "#9e9e9e"
      }
    ]
  }
];

// function initialize_map() {
//   var mapDiv = document.getElementById('map_container');
//   var map = new google.maps.Map(mapDiv, {
//       center: {lat: 24.7136, lng: 46.6753},
//       zoom: 10
//   });
// map.setOptions({styles: styles});
//   var markerlocations = [
//       [26.846694, 80.946166, 'Cloudy Sunny', '../img/map.png'],
//       [28.613939, 77.209021, 'Rainy', '../img/map.png'],
//       [32.7218, 74.8577, 'Snowy', '../img/map.png'],
//       [23.259933, 77.412615, 'Thunderstorm', '../img/map.png'],
//       [23.610181, 85.279935, 'Sunny', '../img/map.png'],
//       [22.986757, 87.854976, 'Storm', '../img/map.png'],
//   ];
//   for(i  = 0;  i < markerlocations.length; i++) {
//       var marker = new google.maps.Marker({
//               position: new google.maps.LatLng(markerlocations[i][0], markerlocations[i][1]),
//               map: map,
//               icon:  markerlocations[i][3]
//       });
//       var address = '<div><p><b>markerlocations[i][2]</b></p></div>';
//       var infowindow = new google.maps.InfoWindow({
//         content: address
//       });
//       marker.addListener('click', function() {
//         infowindow.open(map, marker);
//       });
//   }
// }
