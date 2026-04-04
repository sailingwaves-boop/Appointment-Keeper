<?php
/**
 * ElevenLabs Service - AI Voice Generation
 * Generates natural speech for phone calls
 */

if (!defined('ABSPATH')) {
    exit;
}

class AK_ElevenLabs_Service {
    
    private $api_key;
    private $voice_id;
    private $default_voice_id = 'EXAVITQu4vr4xnSDxMaL'; // Sarah - natural British voice
    
    public function __construct() {
        $this->api_key = get_option('ak_elevenlabs_api_key');
        $this->voice_id = get_option('ak_elevenlabs_voice_id') ?: $this->default_voice_id;
    }
    
    /**
     * Check if ElevenLabs is configured
     */
    public function is_configured() {
        return !empty($this->api_key);
    }
    
    /**
     * Generate speech from text
     * Returns URL to audio file
     */
    public function generate_speech($text, $voice_id = null) {
        if (!$this->is_configured()) {
            return false;
        }
        
        $voice = $voice_id ?: $this->voice_id;
        
        $url = 'https://api.elevenlabs.io/v1/text-to-speech/' . $voice;
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'xi-api-key' => $this->api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'audio/mpeg'
            ),
            'body' => json_encode(array(
                'text' => $text,
                'model_id' => 'eleven_monolingual_v1',
                'voice_settings' => array(
                    'stability' => 0.5,
                    'similarity_boost' => 0.75
                )
            )),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('ElevenLabs error: ' . $response->get_error_message());
            return false;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code !== 200) {
            $body = wp_remote_retrieve_body($response);
            error_log('ElevenLabs API error: ' . $body);
            return false;
        }
        
        // Get the audio data
        $audio_data = wp_remote_retrieve_body($response);
        
        if (empty($audio_data)) {
            return false;
        }
        
        // Save to temp file and return URL
        $upload_dir = wp_upload_dir();
        $audio_dir = $upload_dir['basedir'] . '/ak-audio';
        
        if (!file_exists($audio_dir)) {
            wp_mkdir_p($audio_dir);
            
            // Add index.php for security
            file_put_contents($audio_dir . '/index.php', '<?php // Silence is golden');
        }
        
        // Generate unique filename
        $filename = 'voice_' . md5($text . time()) . '.mp3';
        $filepath = $audio_dir . '/' . $filename;
        $fileurl = $upload_dir['baseurl'] . '/ak-audio/' . $filename;
        
        // Save audio file
        file_put_contents($filepath, $audio_data);
        
        // Schedule cleanup after 1 hour
        wp_schedule_single_event(time() + 3600, 'ak_cleanup_audio_file', array($filepath));
        
        return $fileurl;
    }
    
    /**
     * Get available voices
     */
    public function get_voices() {
        if (!$this->is_configured()) {
            return array();
        }
        
        // Check cache
        $cached = get_transient('ak_elevenlabs_voices');
        if ($cached !== false) {
            return $cached;
        }
        
        $response = wp_remote_get('https://api.elevenlabs.io/v1/voices', array(
            'headers' => array(
                'xi-api-key' => $this->api_key
            )
        ));
        
        if (is_wp_error($response)) {
            return array();
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['voices'])) {
            return array();
        }
        
        $voices = array();
        foreach ($body['voices'] as $voice) {
            $voices[] = array(
                'id' => $voice['voice_id'],
                'name' => $voice['name'],
                'category' => $voice['category'] ?? 'custom'
            );
        }
        
        // Cache for 1 hour
        set_transient('ak_elevenlabs_voices', $voices, 3600);
        
        return $voices;
    }
    
    /**
     * Get remaining character quota
     */
    public function get_quota() {
        if (!$this->is_configured()) {
            return null;
        }
        
        $response = wp_remote_get('https://api.elevenlabs.io/v1/user', array(
            'headers' => array(
                'xi-api-key' => $this->api_key
            )
        ));
        
        if (is_wp_error($response)) {
            return null;
        }
        
        $body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (!isset($body['subscription'])) {
            return null;
        }
        
        return array(
            'used' => $body['subscription']['character_count'],
            'limit' => $body['subscription']['character_limit'],
            'remaining' => $body['subscription']['character_limit'] - $body['subscription']['character_count']
        );
    }
    
    /**
     * Generate appointment reminder message
     */
    public function generate_reminder_message($appointment_data) {
        $name = $appointment_data['customer_name'] ?? 'there';
        $service = $appointment_data['service_name'] ?? 'your appointment';
        $date = $appointment_data['date'] ?? 'soon';
        $time = $appointment_data['time'] ?? '';
        $location = $appointment_data['location'] ?? '';
        
        $message = "Hello {$name}! This is a friendly reminder about {$service}";
        
        if ($date && $time) {
            $message .= " on {$date} at {$time}";
        } elseif ($date) {
            $message .= " on {$date}";
        }
        
        if ($location) {
            $message .= ". The appointment is at {$location}";
        }
        
        $message .= ". We look forward to seeing you! If you need to reschedule, please call us back. Thank you!";
        
        return $message;
    }
}

// Cleanup scheduled audio files
add_action('ak_cleanup_audio_file', function($filepath) {
    if (file_exists($filepath)) {
        unlink($filepath);
    }
});

// Initialize
new AK_ElevenLabs_Service();
