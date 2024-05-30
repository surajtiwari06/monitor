if (!function_exists('generateSVGChart')) {
    function generateSVGChart($data, $width = 300, $height = 150) {
        if (empty($data)) {
            return '<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg"><text x="10" y="20">No data available</text></svg>';
        }

        $maxY = max(array_column($data, 'y'));
        $minY = min(array_column($data, 'y'));
        $rangeY = $maxY - $minY;

        if ($rangeY == 0) {
            $rangeY = 1; // Avoid division by zero
        }

        $svg = '<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg">';
        $svg .= '<polyline fill="none" stroke="blue" stroke-width="2" points="';

        foreach ($data as $point) {
            $x = ($point['x']->getTimestamp() - $data[0]['x']->getTimestamp()) / ($data[count($data) - 1]['x']->getTimestamp() - $data[0]['x']->getTimestamp()) * $width;
            $y = $height - (($point['y'] - $minY) / $rangeY * $height);
            $svg .= $x . ',' . $y . ' ';
        }

        $svg .= '"/>';
        $svg .= '</svg>';

        return $svg;
    }
}
