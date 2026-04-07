<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Example seeder that maps Google Places API types to friendly category names.
 *
 * This mapping is derived from the Places API (New) type list and is a reasonable
 * starting point. Adjust via the category interface to match your preferences.
 */
class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Human-readable label shown in the admin Database Tools page.
     */
    public static function label(): string
    {
        return 'Example Categories';
    }

    /**
     * Insert Google Places API type → friendly name mappings.
     */
    public function run(): void
    {
        $categories = [
            'Other' => [
                // Default
                'default',
                // Business
                'business_center', 'corporate_office', 'coworking_space', 'farm', 'manufacturer',
                'ranch', 'supplier', 'television_studio',
                // Education
                'academic_department', 'educational_institution', 'library', 'preschool', 'primary_school',
                'research_institute', 'school', 'secondary_school', 'university',
                // Facilities
                'public_bath', 'public_bathroom', 'stable',
                // Finance
                'accounting', 'atm', 'bank',
                // Geographical Areas
                'administrative_area_level_1', 'administrative_area_level_2', 'country', 'locality',
                'postal_code', 'school_district',
                // Government
                'city_hall', 'courthouse', 'embassy', 'fire_station', 'government_office',
                'local_government_office', 'neighborhood_police_station', 'police', 'post_office',
                // Health and Wellness
                'massage', 'massage_spa', 'sauna', 'skin_care_clinic', 'spa', 'tanning_studio',
                'wellness_center', 'yoga_studio',
                // Housing
                'apartment_building', 'apartment_complex', 'condominium_complex', 'housing_complex',
                // Places of Worship
                'buddhist_temple', 'church', 'hindu_temple', 'mosque', 'shinto_shrine', 'synagogue',
                // Services
                'aircraft_rental_service', 'association_or_organization', 'astrologer', 'barber_shop',
                'beautician', 'beauty_salon', 'body_art_service', 'catering_service', 'cemetery',
                'chauffeur_service', 'child_care_agency', 'consultant', 'courier_service', 'electrician',
                'employment_agency', 'florist', 'foot_care', 'funeral_home', 'hair_care',
                'hair_salon', 'insurance_agency', 'laundry', 'lawyer', 'locksmith', 'makeup_artist',
                'marketing_consultant', 'moving_company', 'nail_salon', 'non_profit_organization', 'painter',
                'pet_boarding_service', 'pet_care', 'plumber', 'psychic', 'real_estate_agency',
                'roofing_contractor', 'service', 'shipping_service', 'storage', 'summer_camp_organizer',
                'tailor', 'telecommunications_service_provider', 'tourist_information_center', 'veterinary_care',
            ],
            'Automotive' => [
                'car_dealer', 'car_rental', 'car_repair', 'car_wash',
                'parking', 'parking_garage', 'parking_lot', 'rest_stop', 'tire_shop', 'truck_dealer',
            ],
            'Dining' => [
                'acai_shop', 'afghani_restaurant', 'african_restaurant', 'american_restaurant',
                'argentinian_restaurant', 'asian_fusion_restaurant', 'asian_restaurant', 'australian_restaurant',
                'austrian_restaurant', 'bagel_shop', 'bakery', 'bangladeshi_restaurant', 'bar', 'bar_and_grill',
                'barbecue_restaurant', 'basque_restaurant', 'bavarian_restaurant', 'beer_garden',
                'belgian_restaurant', 'bistro', 'brazilian_restaurant', 'breakfast_restaurant', 'brewery',
                'brewpub', 'british_restaurant', 'brunch_restaurant', 'buffet_restaurant', 'burmese_restaurant',
                'burrito_restaurant', 'dessert_restaurant', 'dessert_shop', 'dim_sum_restaurant', 'diner',
                'dog_cafe', 'donut_shop', 'dumpling_restaurant', 'dutch_restaurant', 'eastern_european_restaurant',
                'ethiopian_restaurant', 'european_restaurant', 'falafel_restaurant', 'family_restaurant',
                'fast_food_restaurant', 'filipino_restaurant', 'fine_dining_restaurant', 'fish_and_chips_restaurant',
                'fondue_restaurant', 'food_court', 'french_restaurant', 'german_restaurant', 'greek_restaurant',
                'hamburger_restaurant', 'hawker_centre', 'hawaiian_restaurant', 'ice_cream_shop',
                'indian_restaurant', 'indonesian_restaurant', 'italian_restaurant', 'japanese_restaurant',
                'jewish_restaurant', 'korean_restaurant', 'kosher_restaurant', 'lebanese_restaurant',
                'malaysian_restaurant', 'mediterranean_restaurant', 'mexican_restaurant', 'middle_eastern_restaurant',
                'moroccan_restaurant', 'nepalese_restaurant', 'pizza_restaurant', 'polish_restaurant',
                'portuguese_restaurant', 'ramen_restaurant', 'restaurant', 'russian_restaurant', 'salad_bar',
                'sandwich_shop', 'scandinavian_restaurant', 'seafood_restaurant', 'slovak_restaurant', 'snack_bar',
                'spanish_restaurant', 'sri_lankan_restaurant', 'steak_house', 'sushi_restaurant', 'swiss_restaurant',
                'tapas_restaurant', 'thai_restaurant', 'turkish_restaurant', 'ukrainian_restaurant', 'vegan_restaurant',
                'vegetarian_restaurant', 'vietnamese_restaurant', 'wine_bar', 'food_delivery',
            ],
            'Doctor' => [
                'chiropractor', 'dental_clinic', 'dentist', 'doctor', 'general_hospital',
                'hospital', 'medical_center', 'medical_clinic', 'medical_lab', 'physiotherapist',
            ],
            'Entertainment' => [
                // Culture
                'art_gallery', 'art_museum', 'art_studio', 'auditorium', 'castle', 'cultural_landmark',
                'fountain', 'historical_place', 'history_museum', 'monument', 'museum',
                'performing_arts_theater', 'sculpture',
                // Entertainment and Recreation
                'adventure_sports_center', 'amphitheatre', 'amusement_center', 'amusement_park', 'aquarium',
                'banquet_hall', 'barbecue_area', 'botanical_garden', 'bowling_alley', 'casino', 'childrens_camp',
                'city_park', 'comedy_club', 'community_center', 'concert_hall', 'convention_center',
                'cultural_center', 'cycling_park', 'dance_hall', 'dog_park', 'event_venue', 'ferris_wheel',
                'garden', 'go_karting_venue', 'hiking_area', 'historical_landmark', 'indoor_playground',
                'internet_cafe', 'karaoke', 'live_music_venue', 'marina', 'miniature_golf_course',
                'movie_rental', 'movie_theater', 'national_park', 'night_club', 'observation_deck',
                'off_roading_area', 'opera_house', 'paintball_center', 'park', 'philharmonic_hall',
                'picnic_ground', 'planetarium', 'plaza', 'roller_coaster', 'skateboard_park', 'state_park',
                'tourist_attraction', 'video_arcade', 'vineyard', 'visitor_center', 'water_park',
                'wedding_venue', 'wildlife_park', 'wildlife_refuge', 'zoo',
                // Natural Features
                'beach', 'island', 'lake', 'mountain_peak', 'nature_preserve', 'river', 'scenic_spot', 'woods',
                // Sports
                'arena', 'athletic_field', 'fishing_charter', 'fishing_pier', 'fishing_pond', 'fitness_center',
                'golf_course', 'gym', 'ice_skating_rink', 'indoor_golf_course', 'playground', 'race_course',
                'ski_resort', 'sports_activity_location', 'sports_club', 'sports_coaching', 'sports_complex',
                'sports_school', 'stadium', 'swimming_pool', 'tennis_court',
            ],
            'Gas' => [
                'ebike_charging_station', 'electric_vehicle_charging_station', 'gas_station', 'truck_stop',
            ],
            'Grocery' => [
                'asian_grocery_store', 'butcher_shop', 'grocery_or_supermarket',
                'food_store', 'grocery_store', 'health_food_store',
                'tea_store',
            ],
            'Home Improvement' => [
                'building_materials_store', 'hardware_store', 'home_improvement_store',
            ],
            'Pharmacy' => [
                'drugstore', 'pharmacy',
            ],
            'Shopping' => [
                'auto_parts_store', 'bicycle_store', 'book_store',
                'cell_phone_store', 'clothing_store', 'cosmetics_store', 'department_store',
                'discount_store', 'electronics_store', 'flea_market', 'furniture_store', 'garden_center',
                'general_store', 'gift_shop', 'home_goods_store',
                'jewelry_store', 'liquor_store', 'market', 'pet_store', 'shoe_store', 'shopping_mall',
                'sporting_goods_store', 'sportswear_store', 'store', 'thrift_store', 'toy_store',
                'womens_clothing_store', 'convenience_store', 'discount_supermarket',
                'farmers_market', 'hypermarket', 'supermarket',
            ],
            'Transportation' => [
                'airport', 'airstrip', 'bike_sharing_station', 'bridge', 'bus_station', 'bus_stop',
                'ferry_service', 'ferry_terminal', 'heliport', 'international_airport', 'light_rail_station',
                'park_and_ride', 'subway_station', 'taxi_service', 'taxi_stand', 'toll_station', 'train_station',
                'train_ticket_office', 'tram_stop', 'transit_depot', 'transit_station', 'transit_stop',
                'transportation_service',
            ],
            'Travel' => [
                'bed_and_breakfast', 'budget_japanese_inn', 'campground', 'camping_cabin', 'cottage',
                'extended_stay_hotel', 'farmstay', 'guest_house', 'hostel', 'hotel', 'inn', 'japanese_inn',
                'lodging', 'mobile_home_park', 'motel', 'private_guest_room', 'resort_hotel', 'rv_park',
                'tour_agency', 'travel_agency',
            ],
            'Wholesale' => [
                'wholesaler',
            ],
        ];

        foreach ($categories as $friendlyName => $types) {
            foreach ($types as $type) {
                Category::firstOrCreate(['name' => $type], ['friendly_name' => $friendlyName]);
            }
        }
    }
}
